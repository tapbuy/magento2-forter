<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Observer\OrderValidation;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Validation\ValidationException;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Psr\Log\LoggerInterface;
use Tapbuy\Forter\Api\Data\CheckoutDataInterface;
use Tapbuy\Forter\Exception\PaymentDeclinedException;
use Tapbuy\Forter\Model\RequestBuilder\Order as OrderRequestBuilder;
use Tapbuy\RedirectTracking\Api\TapbuyServiceInterface;

class PaymentPlaceStart implements ObserverInterface
{
    public const PRE_DECISION_KEY = 'forter_pre_decision';
    public const PRE_RECOMMENDATIONS_KEY = 'forter_pre_recommendations';
    public const THREE_DS_AUTH_ON_EXCLUSION_KEY = 'forter_three_ds_auth_on_exclusion';

    private const ORDER_STAGE_BEFORE_PAYMENT = 'BEFORE_PAYMENT_ACTION';

    // Response keys from tapbuy-api (wrapped in {success: true, data: {...}})
    private const RESPONSE_DATA_KEY = 'data';
    private const RESPONSE_STATUS_KEY = 'status';
    private const RESPONSE_FORTER_DECISION_KEY = 'forterDecision';
    private const RESPONSE_RECOMMENDATION_KEY = 'recommendation';
    private const RESPONSE_THREE_DS_AUTH_ON_EXCLUSION_KEY = 'threeDsAuthOnExclusion';

    private const ACTION_DECLINE = 'decline';

    public const DEFAULT_DECLINE_MESSAGE = 'The order can\'t be placed.';

    /**
     * @param TapbuyServiceInterface $tapbuyService
     * @param OrderRequestBuilder $orderRequestBuilder
     * @param LoggerInterface $logger
     * @param CheckoutDataInterface $checkoutData
     * @param Request $request
     */
    public function __construct(
        private readonly TapbuyServiceInterface $tapbuyService,
        private readonly OrderRequestBuilder $orderRequestBuilder,
        private readonly LoggerInterface $logger,
        private readonly CheckoutDataInterface $checkoutData,
        private readonly Request $request
    ) {
    }

    /**
     * Execute fraud detection on payment place start.
     *
     * @param Observer $observer
     * @return void
     * @throws PaymentDeclinedException Is thrown when payment process stop is required.
     */
    public function execute(Observer $observer): void
    {
        $isPaymentDeclined = false;

        try {
            /** @var OrderPaymentInterface $payment */
            $payment = $observer->getEvent()->getPayment();

            // Only process payments from Tapbuy headless checkout.
            if (!$this->request->getHeader('X-Tapbuy-Call')) {
                return;
            }

            // Initialize checkout data from payment additional information
            $this->checkoutData->initFromPayment($payment);

            // Only process if Forter token is present
            if (empty($this->checkoutData->getForterToken())) {
                return;
            }

            $order = $payment->getOrder();

            $payload = $this->orderRequestBuilder->buildFraudDetectionPayload(
                $order,
                $payment,
                self::ORDER_STAGE_BEFORE_PAYMENT
            );

            $response = $this->tapbuyService->sendRequest('/fraud/detection', $payload);

            $this->validateResponse($response);

            // Extract data from response wrapper
            $data = $response[self::RESPONSE_DATA_KEY];
            $forterDecision = strtolower($data[self::RESPONSE_FORTER_DECISION_KEY] ?? '');
            $recommendation = $data[self::RESPONSE_RECOMMENDATION_KEY] ?? '';
            $recommendations = !empty($recommendation) ? [$recommendation] : [];
            $threeDsAuthOnExclusion = $data[self::RESPONSE_THREE_DS_AUTH_ON_EXCLUSION_KEY] ?? CheckoutDataInterface::THREE_DS_AUTH_ALWAYS;

            // Store decision in payment additional information
            $payment->setAdditionalInformation(
                self::PRE_DECISION_KEY,
                $forterDecision
            );
            $payment->setAdditionalInformation(
                self::PRE_RECOMMENDATIONS_KEY,
                $recommendations
            );
            $payment->setAdditionalInformation(
                self::THREE_DS_AUTH_ON_EXCLUSION_KEY,
                $threeDsAuthOnExclusion
            );

            // Also store in CheckoutData for immediate access by ForterDataBuilder
            $this->checkoutData->setThreeDsAuthOnExclusion($threeDsAuthOnExclusion);

            $this->logger->info('Forter fraud detection result', [
                'orderId' => $order->getIncrementId(),
                'status' => $data[self::RESPONSE_STATUS_KEY] ?? null,
                'decision' => $forterDecision,
                'recommendation' => $recommendation
            ]);

            if ($forterDecision !== self::ACTION_DECLINE) {
                return;
            }

            // If Forter recommends 3DS challenge, let the payment proceed to give customer a chance to verify
            if (in_array('VERIFICATION_REQUIRED_3DS_CHALLENGE', $recommendations, true)) {
                return;
            }

            $isPaymentDeclined = true;

            $this->logger->warning('Forter declined order', [
                'orderId' => $order->getIncrementId(),
                'recommendations' => $recommendations
            ]);
        } catch (PaymentDeclinedException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logger->error(
                'Error during payment place start: ' . $e->getMessage(),
                ['exception' => $e->getTraceAsString()]
            );
        }

        if ($isPaymentDeclined) {
            // Break payment process with the error message to the customer.
            throw new PaymentDeclinedException(__($this->getPreDeclineMsg()));
        }
    }

    /**
     * Validate Forter response.
     *
     * @param mixed $response
     * @return void
     * @throws ValidationException
     */
    private function validateResponse(mixed $response): void
    {
        if ($response === false || $response === null) {
            throw new ValidationException(__('No response received from fraud detection service.'));
        }

        if (!is_array($response)) {
            throw new ValidationException(__('Invalid response type from fraud detection service.'));
        }

        // Response is wrapped in {success: true, data: {...}}
        if (!array_key_exists(self::RESPONSE_DATA_KEY, $response) || !is_array($response[self::RESPONSE_DATA_KEY])) {
            throw new ValidationException(__('Unexpected response structure: ' . json_encode($response)));
        }

        $data = $response[self::RESPONSE_DATA_KEY];
        if (!array_key_exists(self::RESPONSE_FORTER_DECISION_KEY, $data)) {
            throw new ValidationException(__('Missing forterDecision in response: ' . json_encode($response)));
        }
    }

    /**
     * Get pre-authorization decline message.
     *
     * @return string
     */
    private function getPreDeclineMsg(): string
    {
        return self::DEFAULT_DECLINE_MESSAGE;
    }
}
