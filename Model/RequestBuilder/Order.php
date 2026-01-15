<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Model\RequestBuilder;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Tapbuy\Forter\Model\RequestBuilder\BasicInfo as BasicInfoRequestBuilder;
use Tapbuy\Forter\Model\RequestBuilder\Cart as CartRequestBuilder;
use Tapbuy\Forter\Model\RequestBuilder\Customer as CustomerRequestBuilder;
use Tapbuy\Forter\Model\RequestBuilder\Payment as PaymentRequestBuilder;

class Order
{
    private const AUTHORIZATION_STEP_PRE = 'PRE_AUTHORIZATION';

    /**
     * @param BasicInfoRequestBuilder $basicInfoRequestBuilder
     * @param CartRequestBuilder $cartRequestBuilder
     * @param CustomerRequestBuilder $customerRequestBuilder
     * @param PaymentRequestBuilder $paymentRequestBuilder
     */
    public function __construct(
        private readonly BasicInfoRequestBuilder $basicInfoRequestBuilder,
        private readonly CartRequestBuilder $cartRequestBuilder,
        private readonly CustomerRequestBuilder $customerRequestBuilder,
        private readonly PaymentRequestBuilder $paymentRequestBuilder
    ) {
    }

    /**
     * Build the payload for fraud detection API call
     *
     * @param OrderInterface $order
     * @param OrderPaymentInterface $payment
     * @param string $orderStage
     * @return array
     */
    public function buildFraudDetectionPayload(
        OrderInterface $order,
        OrderPaymentInterface $payment,
        string $orderStage
    ): array {
        return [
            'authorizationStep' => self::AUTHORIZATION_STEP_PRE,
            'connectionInformation' => $this->basicInfoRequestBuilder->getConnectionInformation($order),
            'order' => [
                'orderNo' => $order->getIncrementId(),
                'creationDate' => $order->getCreatedAt() ? strtotime($order->getCreatedAt()) : time(),
                'currency' => $order->getOrderCurrencyCode(),
                'customer' => [
                    'customerNo' => $order->getCustomerId(),
                    'customerEmail' => $order->getCustomerEmail(),
                    'billingAddress' => $this->customerRequestBuilder->getBillingDetails($order)
                ],
                'shipments' => $this->getShipmentsData($order),
                'totals' => $this->cartRequestBuilder->getTotalAmount($order),
                'items' => $this->cartRequestBuilder->getCartItems($order),
                'totalDiscount' => $this->cartRequestBuilder->getTotalDiscount($order),
                'payments' => [$this->paymentRequestBuilder->getPaymentData($order, $payment)]
            ],
            'primaryDeliveryDetails' => $this->customerRequestBuilder->getPrimaryDeliveryDetails($order),
            'primaryRecipient' => $this->customerRequestBuilder->getPrimaryRecipient($order),
            'accountOwner' => $this->customerRequestBuilder->getAccountOwnerInfo($order),
            'customerAccountData' => $this->customerRequestBuilder->getCustomerAccountData($order),
            'additionalIdentifiers' => $this->basicInfoRequestBuilder->getAdditionalIdentifiers($order, $orderStage)
        ];
    }

    /**
     * Get shipments data.
     *
     * @param OrderInterface $order
     * @return array
     */
    private function getShipmentsData(OrderInterface $order): array
    {
        $shippingAddress = $order->getShippingAddress();

        if ($shippingAddress === null) {
            return [];
        }

        $street = $shippingAddress->getStreet();

        return [
            [
                'shippingMethod' => $order->getShippingMethod(),
                'shippingPrice' => $order->getShippingAmount(),
                'shippingAddress' => [
                    'firstName' => $shippingAddress->getFirstname(),
                    'lastName' => $shippingAddress->getLastname(),
                    'address1' => $street[0] ?? '',
                    'address2' => $street[1] ?? '',
                    'city' => $shippingAddress->getCity(),
                    'postalCode' => $shippingAddress->getPostcode(),
                    'countryCode' => $shippingAddress->getCountryId(),
                    'region' => $shippingAddress->getRegion() ?? '',
                    'company' => $shippingAddress->getCompany() ?? '',
                    'phone' => $shippingAddress->getTelephone()
                ]
            ]
        ];
    }
}
