<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Test\Unit\Model\RequestBuilder;

use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\Forter\Api\Data\CheckoutDataInterface;
use Tapbuy\Forter\Model\RequestBuilder\Payment;

class PaymentTest extends TestCase
{
    private Payment $builder;
    private CheckoutDataInterface&MockObject $checkoutData;
    private Json&MockObject $json;

    protected function setUp(): void
    {
        $this->checkoutData = $this->createMock(CheckoutDataInterface::class);
        $this->json = $this->createMock(Json::class);

        $this->builder = new Payment($this->checkoutData, $this->json);
    }

    public function testGetPaymentDataReturnsMethodAndAmount(): void
    {
        $this->checkoutData->method('getCollectedForterData')->willReturn(null);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getGrandTotal')->willReturn(100.00);

        $payment = $this->createMock(OrderPaymentInterface::class);
        $payment->method('getMethod')->willReturn('adyen_cc');
        $payment->method('getAmountOrdered')->willReturn(100.00);

        $result = $this->builder->getPaymentData($order, $payment);

        $this->assertSame('adyen_cc', $result['paymentMethod']);
        $this->assertSame(100.00, $result['amount']);
        $this->assertSame([], $result['card']);
    }

    public function testGetPaymentDataFallsBackToGrandTotalWhenAmountOrderedIsNull(): void
    {
        $this->checkoutData->method('getCollectedForterData')->willReturn(null);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getGrandTotal')->willReturn(99.99);

        $payment = $this->createMock(OrderPaymentInterface::class);
        $payment->method('getMethod')->willReturn('checkmo');
        $payment->method('getAmountOrdered')->willReturn(null);

        $result = $this->builder->getPaymentData($order, $payment);

        $this->assertSame(99.99, $result['amount']);
    }

    public function testGetPaymentDataExtractsCardDetails(): void
    {
        $collectedData = json_encode([
            'cardBrand' => 'visa',
            'cardBin' => '411111',
            'cardLast4Digits' => '1234',
            'cardHolderName' => 'John Doe',
        ]);

        $this->checkoutData->method('getCollectedForterData')->willReturn($collectedData);
        $this->json->method('unserialize')->with($collectedData)->willReturn([
            'cardBrand' => 'visa',
            'cardBin' => '411111',
            'cardLast4Digits' => '1234',
            'cardHolderName' => 'John Doe',
        ]);

        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(OrderPaymentInterface::class);
        $payment->method('getMethod')->willReturn('adyen_cc');
        $payment->method('getAmountOrdered')->willReturn(50.00);

        $result = $this->builder->getPaymentData($order, $payment);

        $this->assertSame('visa', $result['card']['cardType']);
        $this->assertSame('411111', $result['card']['cardBin']);
        $this->assertSame('1234', $result['card']['cardLastDigits']);
        $this->assertSame('John Doe', $result['card']['cardHolder']);
    }

    public function testGetPaymentDataReturnsPartialCardDetails(): void
    {
        $collectedData = json_encode(['cardBrand' => 'mc']);

        $this->checkoutData->method('getCollectedForterData')->willReturn($collectedData);
        $this->json->method('unserialize')->willReturn(['cardBrand' => 'mc']);

        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(OrderPaymentInterface::class);
        $payment->method('getMethod')->willReturn('adyen_cc');
        $payment->method('getAmountOrdered')->willReturn(50.00);

        $result = $this->builder->getPaymentData($order, $payment);

        $this->assertSame('mc', $result['card']['cardType']);
        $this->assertArrayNotHasKey('cardBin', $result['card']);
    }

    public function testGetPaymentDataThrowsOnMalformedCollectedData(): void
    {
        $this->checkoutData->method('getCollectedForterData')->willReturn('bad-json');
        $this->json->method('unserialize')
            ->willThrowException(new \InvalidArgumentException('Invalid JSON'));

        $this->expectException(InvalidArgumentException::class);

        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(OrderPaymentInterface::class);
        $payment->method('getMethod')->willReturn('adyen_cc');
        $payment->method('getAmountOrdered')->willReturn(50.00);

        $this->builder->getPaymentData($order, $payment);
    }
}
