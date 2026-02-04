<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Plugin\Order;

use Exception;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Sales\Model\Order\Payment as MagentoPayment;
use Tapbuy\Forter\Api\Data\CheckoutDataInterface;
use Tapbuy\Forter\Exception\PaymentDeclinedException;
use Tapbuy\Forter\Model\RequestBuilder\Order as OrderRequestBuilder;
use Tapbuy\RedirectTracking\Api\TapbuyServiceInterface;
use Tapbuy\RedirectTracking\Logger\TapbuyLogger;

class Payment
{
    private const ORDER_STAGE_FAILURE = 'PAYMENT_ACTION_FAILURE';

    /**
     * @param TapbuyServiceInterface $tapbuyService
     * @param OrderRequestBuilder $orderRequestBuilder
     * @param CheckoutDataInterface $checkoutData
     * @param Request $request
     * @param TapbuyLogger $logger
     */
    public function __construct(
        private readonly TapbuyServiceInterface $tapbuyService,
        private readonly OrderRequestBuilder $orderRequestBuilder,
        private readonly CheckoutDataInterface $checkoutData,
        private readonly Request $request,
        private readonly TapbuyLogger $logger
    ) {
    }

    /**
     * Catch exceptions during a payment placement to send them to Forter.
     *
     * @param MagentoPayment $subject
     * @param callable $proceed
     * @return MagentoPayment
     * @throws Exception
     */
    public function aroundPlace(MagentoPayment $subject, callable $proceed): MagentoPayment
    {
        try {
            return $proceed();
        } catch (Exception $e) {
            $this->notifyForterOfPaymentFailure($e, $subject);
            throw $e;
        }
    }

    /**
     * Send exception notification to TapBuy/Forter.
     *
     * @param Exception $e
     * @param MagentoPayment $subject
     * @return void
     */
    private function notifyForterOfPaymentFailure(Exception $e, MagentoPayment $subject): void
    {
        try {
            // Only process payments from Tapbuy headless checkout
            if (!$this->request->getHeader('X-Tapbuy-Call')) {
                return;
            }

            // Initialize checkout data from payment additional information
            $this->checkoutData->initFromPayment($subject);

            // Only process if Forter token is present
            if (empty($this->checkoutData->getForterToken())) {
                return;
            }

            if ($e instanceof PaymentDeclinedException) {
                // Do not report payment decline exception thrown by Forter itself.
                return;
            }

            $order = $subject->getOrder();

            $payload = $this->orderRequestBuilder->buildFraudDetectionPayload(
                $order,
                $subject,
                self::ORDER_STAGE_FAILURE
            );

            $this->tapbuyService->sendRequest('/fraud/detection', $payload);

            $this->logger->info('Forter payment failure notification sent', [
                'order_id' => $order->getIncrementId(),
                'original_exception' => $e->getMessage(),
            ]);
        } catch (Exception $ex) {
            $this->logger->logException('Failed to send Forter payment failure notification', $ex, [
                'order_id' => $order->getIncrementId() ?? null,
            ]);
        }
    }
}
