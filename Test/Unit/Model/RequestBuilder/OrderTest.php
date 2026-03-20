<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Test\Unit\Model\RequestBuilder;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\Forter\Api\RequestBuilder\BasicInfoBuilderInterface;
use Tapbuy\Forter\Api\RequestBuilder\CartBuilderInterface;
use Tapbuy\Forter\Api\RequestBuilder\CustomerBuilderInterface;
use Tapbuy\Forter\Api\RequestBuilder\PaymentBuilderInterface;
use Tapbuy\Forter\Model\RequestBuilder\Order;

class OrderTest extends TestCase
{
    private Order $builder;
    private BasicInfoBuilderInterface&MockObject $basicInfo;
    private CartBuilderInterface&MockObject $cart;
    private CustomerBuilderInterface&MockObject $customer;
    private PaymentBuilderInterface&MockObject $payment;

    protected function setUp(): void
    {
        $this->basicInfo = $this->createMock(BasicInfoBuilderInterface::class);
        $this->cart = $this->createMock(CartBuilderInterface::class);
        $this->customer = $this->createMock(CustomerBuilderInterface::class);
        $this->payment = $this->createMock(PaymentBuilderInterface::class);

        $this->builder = new Order(
            $this->basicInfo,
            $this->cart,
            $this->customer,
            $this->payment
        );
    }

    public function testBuildFraudDetectionPayloadReturnsCompleteStructure(): void
    {
        $this->basicInfo->method('getConnectionInformation')->willReturn(['customerIP' => '127.0.0.1']);
        $this->basicInfo->method('getAdditionalIdentifiers')->willReturn(['merchant' => []]);
        $this->cart->method('getTotalAmount')->willReturn(['orderTotal' => ['grossPrice' => 100]]);
        $this->cart->method('getCartItems')->willReturn([['name' => 'Item']]);
        $this->cart->method('getTotalDiscount')->willReturn(null);
        $this->customer->method('getBillingDetails')->willReturn(['firstName' => 'John']);
        $this->customer->method('getPrimaryDeliveryDetails')->willReturn(['deliveryType' => 'PHYSICAL']);
        $this->customer->method('getPrimaryRecipient')->willReturn(['personalDetails' => []]);
        $this->customer->method('getAccountOwnerInfo')->willReturn(['email' => 'j@example.com']);
        $this->customer->method('getCustomerAccountData')->willReturn(['customerEngagement' => []]);
        $this->payment->method('getPaymentData')->willReturn(['paymentMethod' => 'adyen_cc']);

        $shippingAddress = $this->createMock(OrderAddressInterface::class);
        $shippingAddress->method('getFirstname')->willReturn('John');
        $shippingAddress->method('getLastname')->willReturn('Doe');
        $shippingAddress->method('getStreet')->willReturn(['123 St']);
        $shippingAddress->method('getCity')->willReturn('Paris');
        $shippingAddress->method('getPostcode')->willReturn('75001');
        $shippingAddress->method('getCountryId')->willReturn('FR');
        $shippingAddress->method('getRegion')->willReturn('IDF');
        $shippingAddress->method('getCompany')->willReturn(null);
        $shippingAddress->method('getTelephone')->willReturn('+33100000000');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getCreatedAt')->willReturn('2024-01-15 10:00:00');
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $order->method('getCustomerId')->willReturn(42);
        $order->method('getCustomerEmail')->willReturn('j@example.com');
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->method('getShippingMethod')->willReturn('flatrate_flatrate');
        $order->method('getShippingAmount')->willReturn(5.99);

        $paymentInterface = $this->createMock(OrderPaymentInterface::class);

        $result = $this->builder->buildFraudDetectionPayload($order, $paymentInterface, 'BEFORE_PAYMENT_ACTION');

        $this->assertSame('PRE_AUTHORIZATION', $result['authorizationStep']);
        $this->assertArrayHasKey('connectionInformation', $result);
        $this->assertArrayHasKey('order', $result);
        $this->assertSame('100000001', $result['order']['orderNo']);
        $this->assertArrayHasKey('primaryDeliveryDetails', $result);
        $this->assertArrayHasKey('primaryRecipient', $result);
        $this->assertArrayHasKey('accountOwner', $result);
        $this->assertArrayHasKey('additionalIdentifiers', $result);
    }

    public function testBuildFraudDetectionPayloadHandlesNoShippingAddress(): void
    {
        $this->basicInfo->method('getConnectionInformation')->willReturn([]);
        $this->basicInfo->method('getAdditionalIdentifiers')->willReturn([]);
        $this->cart->method('getTotalAmount')->willReturn([]);
        $this->cart->method('getCartItems')->willReturn([]);
        $this->cart->method('getTotalDiscount')->willReturn(null);
        $this->customer->method('getBillingDetails')->willReturn([]);
        $this->customer->method('getPrimaryDeliveryDetails')->willReturn([]);
        $this->customer->method('getPrimaryRecipient')->willReturn([]);
        $this->customer->method('getAccountOwnerInfo')->willReturn([]);
        $this->customer->method('getCustomerAccountData')->willReturn([]);
        $this->payment->method('getPaymentData')->willReturn([]);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000002');
        $order->method('getCreatedAt')->willReturn(null);
        $order->method('getOrderCurrencyCode')->willReturn('USD');
        $order->method('getCustomerId')->willReturn(null);
        $order->method('getCustomerEmail')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);

        $paymentInterface = $this->createMock(OrderPaymentInterface::class);

        $result = $this->builder->buildFraudDetectionPayload($order, $paymentInterface, 'STAGE');

        $this->assertSame([], $result['order']['shipments']);
    }
}
