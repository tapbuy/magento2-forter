<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Test\Unit\Model\RequestBuilder;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\Forter\Api\Data\CheckoutDataInterface;
use Tapbuy\Forter\Model\RequestBuilder\BasicInfo;

class BasicInfoTest extends TestCase
{
    private BasicInfo $builder;
    private CheckoutDataInterface&MockObject $checkoutData;
    private RemoteAddress&MockObject $remote;
    private RequestInterface&MockObject $request;

    protected function setUp(): void
    {
        $this->checkoutData = $this->createMock(CheckoutDataInterface::class);
        $this->remote = $this->createMock(RemoteAddress::class);
        $this->request = $this->getMockBuilder(RequestInterface::class)
            ->addMethods(['getHeader'])
            ->getMockForAbstractClass();

        $this->builder = new BasicInfo(
            $this->checkoutData,
            $this->remote,
            $this->request
        );
    }

    public function testGetConnectionInformationReturnsEmptyForCyberSourceUserAgent(): void
    {
        $this->request->method('getHeader')->with('User-Agent')->willReturn('CyberSource SDK');

        $order = $this->createOrderMock();

        $this->assertSame([], $this->builder->getConnectionInformation($order));
    }

    public function testGetConnectionInformationReturnsFullDataForNormalRequest(): void
    {
        $this->request->method('getHeader')->with('User-Agent')->willReturn('Mozilla/5.0');
        $this->checkoutData->method('getForterToken')->willReturn('forter-token-123');

        $order = $this->createOrderMock('192.168.1.1');

        $result = $this->builder->getConnectionInformation($order);

        $this->assertSame('192.168.1.1', $result['customerIP']);
        $this->assertSame('Mozilla/5.0', $result['userAgent']);
        $this->assertSame('forter-token-123', $result['fraudDetectionCookie']);
        $this->assertNull($result['merchantDeviceIdentifier']);
    }

    public function testGetConnectionInformationFallsBackToRemoteAddress(): void
    {
        $this->request->method('getHeader')->with('User-Agent')->willReturn('Mozilla/5.0');
        $this->remote->method('getRemoteAddress')->willReturn('10.0.0.1');

        $order = $this->createOrderMock(null);

        $result = $this->builder->getConnectionInformation($order);

        $this->assertSame('10.0.0.1', $result['customerIP']);
    }

    public function testGetConnectionInformationReturnsEmptyStringWhenNoIp(): void
    {
        $this->request->method('getHeader')->with('User-Agent')->willReturn('Mozilla/5.0');
        $this->remote->method('getRemoteAddress')->willReturn(false);

        $order = $this->createOrderMock(null);

        $result = $this->builder->getConnectionInformation($order);

        $this->assertSame('', $result['customerIP']);
    }

    public function testGetConnectionInformationTruncatesLongUserAgent(): void
    {
        $longUA = str_repeat('A', 5000);
        $this->request->method('getHeader')->with('User-Agent')->willReturn($longUA);

        $order = $this->createOrderMock();

        $result = $this->builder->getConnectionInformation($order);

        $this->assertSame(4000, strlen($result['userAgent']));
    }

    public function testGetConnectionInformationHandlesNullUserAgent(): void
    {
        $this->request->method('getHeader')->with('User-Agent')->willReturn(null);

        $order = $this->createOrderMock();

        $result = $this->builder->getConnectionInformation($order);

        $this->assertSame('', $result['userAgent']);
    }

    public function testGetAdditionalIdentifiersReturnsStoreInfoAndOrderStage(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getUrl')->willReturn('https://store.example.com/');
        $store->method('getName')->willReturn('Test Store');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getStore')->willReturn($store);

        $result = $this->builder->getAdditionalIdentifiers($order, 'BEFORE_PAYMENT_ACTION');

        $this->assertSame('https://store.example.com/', $result['merchant']['merchantDomain']);
        $this->assertSame('Test Store', $result['merchant']['merchantName']);
        $this->assertSame('BEFORE_PAYMENT_ACTION', $result['magentoAdditionalOrderData']['magentoOrderStage']);
    }

    private function createOrderMock(?string $remoteIp = '127.0.0.1'): OrderInterface&MockObject
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getRemoteIp')->willReturn($remoteIp);
        return $order;
    }
}
