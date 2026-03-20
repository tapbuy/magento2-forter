<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Test\Unit\Plugin\Order;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment as MagentoPayment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\Forter\Api\Data\CheckoutDataInterface;
use Tapbuy\Forter\Api\RequestBuilder\OrderBuilderInterface;
use Tapbuy\Forter\Exception\PaymentDeclinedException;
use Tapbuy\Forter\Plugin\Order\Payment;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;
use Tapbuy\RedirectTracking\Api\TapbuyServiceInterface;

class PaymentTest extends TestCase
{
    private Payment $plugin;
    private TapbuyServiceInterface&MockObject $tapbuyService;
    private OrderBuilderInterface&MockObject $orderRequestBuilder;
    private CheckoutDataInterface&MockObject $checkoutData;
    private TapbuyRequestDetectorInterface&MockObject $requestDetector;
    private ConfigInterface&MockObject $config;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->tapbuyService = $this->createMock(TapbuyServiceInterface::class);
        $this->orderRequestBuilder = $this->createMock(OrderBuilderInterface::class);
        $this->checkoutData = $this->createMock(CheckoutDataInterface::class);
        $this->requestDetector = $this->createMock(TapbuyRequestDetectorInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->plugin = new Payment(
            $this->tapbuyService,
            $this->orderRequestBuilder,
            $this->checkoutData,
            $this->requestDetector,
            $this->config,
            $this->logger
        );
    }

    public function testAroundPlaceReturnsResultOnSuccess(): void
    {
        $subject = $this->createMock(MagentoPayment::class);
        $proceed = fn () => $subject;

        $result = $this->plugin->aroundPlace($subject, $proceed);

        $this->assertSame($subject, $result);
    }

    public function testAroundPlaceRethrowsOriginalException(): void
    {
        $subject = $this->createMock(MagentoPayment::class);

        // Not a Tapbuy call → no Forter notification but exception still re-thrown
        $this->requestDetector->method('isTapbuyCall')->willReturn(false);

        $originalException = new \RuntimeException('Payment gateway failed');
        $proceed = function () use ($originalException) {
            throw $originalException;
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payment gateway failed');

        $this->plugin->aroundPlace($subject, $proceed);
    }

    public function testAroundPlaceSendsForterNotificationOnFailure(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000001');

        $subject = $this->createMock(MagentoPayment::class);
        $subject->method('getOrder')->willReturn($order);

        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(true);
        $this->checkoutData->method('getForterToken')->willReturn('forter-token');

        $this->orderRequestBuilder->method('buildFraudDetectionPayload')->willReturn(['payload']);
        $this->tapbuyService->expects($this->once())
            ->method('sendRequest')
            ->with('/fraud/detection', ['payload']);

        $this->logger->expects($this->once())->method('info');

        $originalException = new \RuntimeException('Gateway error');
        $proceed = function () use ($originalException) {
            throw $originalException;
        };

        try {
            $this->plugin->aroundPlace($subject, $proceed);
            $this->fail('Expected RuntimeException to be re-thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Gateway error', $e->getMessage());
        }
    }

    public function testSkipsNotificationWhenNotTapbuyCall(): void
    {
        $subject = $this->createMock(MagentoPayment::class);
        $this->requestDetector->method('isTapbuyCall')->willReturn(false);

        $this->tapbuyService->expects($this->never())->method('sendRequest');

        $proceed = function () {
            throw new \RuntimeException('Error');
        };

        $this->expectException(\RuntimeException::class);
        $this->plugin->aroundPlace($subject, $proceed);
    }

    public function testSkipsNotificationWhenDisabled(): void
    {
        $subject = $this->createMock(MagentoPayment::class);
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(false);

        $this->tapbuyService->expects($this->never())->method('sendRequest');

        $proceed = function () {
            throw new \RuntimeException('Error');
        };

        $this->expectException(\RuntimeException::class);
        $this->plugin->aroundPlace($subject, $proceed);
    }

    public function testSkipsNotificationWhenNoForterToken(): void
    {
        $subject = $this->createMock(MagentoPayment::class);
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(true);
        $this->checkoutData->method('getForterToken')->willReturn(null);

        $this->tapbuyService->expects($this->never())->method('sendRequest');

        $proceed = function () {
            throw new \RuntimeException('Error');
        };

        $this->expectException(\RuntimeException::class);
        $this->plugin->aroundPlace($subject, $proceed);
    }

    public function testSkipsNotificationForPaymentDeclinedException(): void
    {
        $subject = $this->createMock(MagentoPayment::class);
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(true);
        $this->checkoutData->method('getForterToken')->willReturn('forter-token');

        // PaymentDeclinedException should NOT trigger a Forter notification
        $this->tapbuyService->expects($this->never())->method('sendRequest');

        $proceed = function () {
            throw new PaymentDeclinedException(__('Declined by Forter'));
        };

        $this->expectException(PaymentDeclinedException::class);
        $this->plugin->aroundPlace($subject, $proceed);
    }

    public function testNotificationFailureDoesNotReplaceOriginalException(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000002');

        $subject = $this->createMock(MagentoPayment::class);
        $subject->method('getOrder')->willReturn($order);

        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(true);
        $this->checkoutData->method('getForterToken')->willReturn('forter-token');

        $this->orderRequestBuilder->method('buildFraudDetectionPayload')
            ->willThrowException(new \RuntimeException('Builder broken'));

        $this->logger->expects($this->once())->method('logException');

        $originalException = new \RuntimeException('Original gateway error');
        $proceed = function () use ($originalException) {
            throw $originalException;
        };

        try {
            $this->plugin->aroundPlace($subject, $proceed);
            $this->fail('Expected original exception to be re-thrown');
        } catch (\RuntimeException $e) {
            // Must be the ORIGINAL exception, not the builder's
            $this->assertSame('Original gateway error', $e->getMessage());
        }
    }
}
