<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Model\RequestBuilder;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use InvalidArgumentException as BaseInvalidArgumentException;
use Magento\Framework\Exception\InvalidArgumentException;
use Tapbuy\Forter\Api\Data\CheckoutDataInterface;
use Tapbuy\Forter\Api\RequestBuilder\PaymentBuilderInterface;

class Payment implements PaymentBuilderInterface
{
    /**
     * @param CheckoutDataInterface $checkoutData
     * @param Json $json
     */
    public function __construct(
        private readonly CheckoutDataInterface $checkoutData,
        private readonly Json $json
    ) {
    }

    /**
     * Get payment data.
     *
     * @param OrderInterface $order
     * @param OrderPaymentInterface $payment
     * @return array
     * @throws InvalidArgumentException
     */
    public function getPaymentData(OrderInterface $order, OrderPaymentInterface $payment): array
    {
        return [
            'paymentMethod' => $payment->getMethod(),
            'amount' => $payment->getAmountOrdered() ?? $order->getGrandTotal(),
            'card' => $this->getCardDetails()
        ];
    }

    /**
     * Get card details.
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private function getCardDetails(): array
    {
        $cardDetails = [];
        $collectedData = $this->checkoutData->getCollectedForterData();

        if ($collectedData === null) {
            return $cardDetails;
        }

        try {
            $collectedForterData = $this->json->unserialize($collectedData);
        } catch (BaseInvalidArgumentException $e) {
            throw new InvalidArgumentException(__(
                'Error getting card details. Collected data: ' . $collectedData .
                ' Error: ' . $e->getMessage()
            ));
        }

        if (isset($collectedForterData[CheckoutDataInterface::CARD_BRAND_KEY])) {
            $cardDetails['cardType'] = $collectedForterData[CheckoutDataInterface::CARD_BRAND_KEY];
        }

        if (isset($collectedForterData[CheckoutDataInterface::CARD_BIN_KEY])) {
            $cardDetails['cardBin'] = $collectedForterData[CheckoutDataInterface::CARD_BIN_KEY];
        }

        if (isset($collectedForterData[CheckoutDataInterface::CARD_LAST_4_DIGITS_KEY])) {
            $cardDetails['cardLastDigits'] = $collectedForterData[CheckoutDataInterface::CARD_LAST_4_DIGITS_KEY];
        }

        if (isset($collectedForterData[CheckoutDataInterface::CARD_HOLDER_NAME_KEY])) {
            $cardDetails['cardHolder'] = $collectedForterData[CheckoutDataInterface::CARD_HOLDER_NAME_KEY];
        }

        return $cardDetails;
    }
}
