<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Test\Unit\Model\RequestBuilder;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Tapbuy\Forter\Model\RequestBuilder\Cart;

class CartTest extends TestCase
{
    private Cart $builder;

    protected function setUp(): void
    {
        $this->builder = new Cart();
    }

    public function testGetTotalAmountReturnsGrandTotal(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getGrandTotal')->willReturn(150.50);

        $result = $this->builder->getTotalAmount($order);

        $this->assertSame(150.50, $result['orderTotal']['grossPrice']);
    }

    public function testGetCartItemsReturnsVisibleItems(): void
    {
        $item1 = $this->createMock(OrderItemInterface::class);
        $item1->method('getName')->willReturn('T-Shirt');
        $item1->method('getQtyOrdered')->willReturn(2.0);
        $item1->method('getPrice')->willReturn(25.00);
        $item1->method('getProductId')->willReturn(101);
        $item1->method('getSku')->willReturn('TSH-001');

        $item2 = $this->createMock(OrderItemInterface::class);
        $item2->method('getName')->willReturn('Jeans');
        $item2->method('getQtyOrdered')->willReturn(1.0);
        $item2->method('getPrice')->willReturn(49.99);
        $item2->method('getProductId')->willReturn(102);
        $item2->method('getSku')->willReturn('JNS-002');

        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $order->method('getAllVisibleItems')->willReturn([$item1, $item2]);

        $result = $this->builder->getCartItems($order);

        $this->assertCount(2, $result);
        $this->assertSame('T-Shirt', $result[0]['name']);
        $this->assertSame(2, $result[0]['qty']);
        $this->assertSame('Jeans', $result[1]['name']);
    }

    public function testGetCartItemsReturnsEmptyForEmptyOrder(): void
    {
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $order->method('getAllVisibleItems')->willReturn([]);

        $this->assertSame([], $this->builder->getCartItems($order));
    }

    public function testGetTotalDiscountReturnsNullWhenNoDiscount(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getDiscountAmount')->willReturn(null);

        $this->assertNull($this->builder->getTotalDiscount($order));
    }

    public function testGetTotalDiscountReturnsNullWhenDiscountIsZero(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getDiscountAmount')->willReturn(0.0);

        $this->assertNull($this->builder->getTotalDiscount($order));
    }

    public function testGetTotalDiscountReturnsAbsoluteAmount(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getDiscountAmount')->willReturn(-15.50);
        $order->method('getCouponCode')->willReturn('SAVE15');
        $order->method('getOrderCurrencyCode')->willReturn('EUR');

        $result = $this->builder->getTotalDiscount($order);

        $this->assertSame('SAVE15', $result['couponCodeUsed']);
        $this->assertSame('15.5', $result['discountAmount']['amountLocalCurrency']);
        $this->assertSame('EUR', $result['discountAmount']['currency']);
    }

    public function testGetTotalDiscountHandlesPositiveDiscountAmount(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getDiscountAmount')->willReturn(10.00);
        $order->method('getCouponCode')->willReturn(null);
        $order->method('getOrderCurrencyCode')->willReturn('USD');

        $result = $this->builder->getTotalDiscount($order);

        $this->assertSame('10', $result['discountAmount']['amountLocalCurrency']);
        $this->assertNull($result['couponCodeUsed']);
    }
}
