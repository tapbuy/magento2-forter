<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Test\Unit\Observer\OrderValidation;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event;
use Magento\Framework\Validation\ValidationException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\Forter\Api\Data\CheckoutDataInterface;
use Tapbuy\Forter\Api\RequestBuilder\OrderBuilderInterface;
use Tapbuy\Forter\Exception\PaymentDeclinedException;
use Tapbuy\Forter\Model\ForterResponseParser;
use Tapbuy\Forter\Observer\OrderValidation\PaymentPlaceStart;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;
use Tapbuy\RedirectTracking\Api\TapbuyServiceInterface;

class PaymentPlaceStartTest extends TestCase
{
    private PaymentPlaceStart $observer;
    private TapbuyServiceInterface&MockObject $tapbuyService;
    private OrderBuilderInterface&MockObject $orderRequestBuilder;
    private LoggerInterface&MockObject $logger;
    private CheckoutDataInterface&MockObject $checkoutData;
    private TapbuyRequestDetectorInterface&MockObject $requestDetector;
    private ConfigInterface&MockObject $config;
    private ForterResponseParser&MockObject $responseParser;

    protected function setUp(): void
    {
        $this->tapbuyService = $this->createMock(TapbuyServiceInterface::class);
        $this->orderRequestBuilder = $this->createMock(OrderBuilderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->checkoutData = $this->createMock(CheckoutDataInterface::class);
        $this->requestDetector = $this->createMock(TapbuyRequestDetectorInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->responseParser = $this->createMock(ForterResponseParser::class);

        $this->observer = new PaymentPlaceStart(
            $this->tapbuyService,
            $this->orderRequestBuilder,
            $this->logger,
            $this->checkoutData,
            $this->requestDetector,
            $this->config,
            $this->responseParser
        );
    }

    private function createObserverWithPayment(): array
    {
        $payment = $this->getMockBuilder(OrderPayment::class)->disableOriginalConstructor()->getMock();
        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getPayment'])
            ->getMock();
        $event->method('getPayment')->willReturn($payment);

        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return [$observer, $payment];
    }

    public function testSkipsWhenNotTapbuyCall(): void
    {
        [$observer, $payment] = $this->createObserverWithPayment();
        $this->requestDetector->method('isTapbuyCall')->willReturn(false);
        $this->config->method('isEnabled')->willReturn(true);

        $this->tapbuyService->expects($this->never())->method('sendRequest');

        $this->observer->execute($observer);
    }

    public function testSkipsWhenDisabled(): void
    {
        [$observer, $payment] = $this->createObserverWithPayment();
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(false);

        $this->tapbuyService->expects($this->never())->method('sendRequest');

        $this->observer->execute($observer);
    }

    public function testSkipsWhenNoForterToken(): void
    {
        [$observer, $payment] = $this->createObserverWithPayment();
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(true);
        $this->checkoutData->method('getForterToken')->willReturn(null);

        $this->tapbuyService->expects($this->never())->method('sendRequest');

        $this->observer->execute($observer);
    }

    public function testApproveDecisionDoesNotThrow(): void
    {
        [$observer, $payment] = $this->createObserverWithPayment();
        $this->enableForter('forter-token-123');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000001');
        $payment->method('getOrder')->willReturn($order);

        $this->orderRequestBuilder->method('buildFraudDetectionPayload')->willReturn(['payload']);
        $this->tapbuyService->method('sendRequest')->willReturn(['data' => ['forterDecision' => 'approve']]);
        $this->responseParser->method('parse')->willReturn([
            ForterResponseParser::RESPONSE_FORTER_DECISION_KEY => 'approve',
            ForterResponseParser::RESPONSE_STATUS_KEY => 'success',
        ]);

        $payment->expects($this->atLeastOnce())->method('setAdditionalInformation');

        $this->observer->execute($observer);
    }

    public function testDeclineThrowsPaymentDeclinedException(): void
    {
        [$observer, $payment] = $this->createObserverWithPayment();
        $this->enableForter('forter-token-123');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000002');
        $payment->method('getOrder')->willReturn($order);

        $this->orderRequestBuilder->method('buildFraudDetectionPayload')->willReturn(['payload']);
        $this->tapbuyService->method('sendRequest')->willReturn(['raw']);
        $this->responseParser->method('parse')->willReturn([
            ForterResponseParser::RESPONSE_FORTER_DECISION_KEY => 'decline',
        ]);

        $this->expectException(PaymentDeclinedException::class);

        $this->observer->execute($observer);
    }

    public function testDeclineWithThreeDsChallengeBypassesDecline(): void
    {
        [$observer, $payment] = $this->createObserverWithPayment();
        $this->enableForter('forter-token-123');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000003');
        $payment->method('getOrder')->willReturn($order);

        $this->orderRequestBuilder->method('buildFraudDetectionPayload')->willReturn(['payload']);
        $this->tapbuyService->method('sendRequest')->willReturn(['raw']);
        $this->responseParser->method('parse')->willReturn([
            ForterResponseParser::RESPONSE_FORTER_DECISION_KEY => 'decline',
            ForterResponseParser::RESPONSE_RECOMMENDATION_KEY => 'VERIFICATION_REQUIRED_3DS_CHALLENGE',
        ]);

        $this->logger->expects($this->atLeastOnce())->method('info');

        // Should NOT throw — 3DS challenge bypasses decline
        $this->observer->execute($observer);
    }

    public function testStoresDecisionAndRecommendationsInPayment(): void
    {
        [$observer, $payment] = $this->createObserverWithPayment();
        $this->enableForter('forter-token-123');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000004');
        $payment->method('getOrder')->willReturn($order);

        $this->orderRequestBuilder->method('buildFraudDetectionPayload')->willReturn(['payload']);
        $this->tapbuyService->method('sendRequest')->willReturn(['raw']);
        $this->responseParser->method('parse')->willReturn([
            ForterResponseParser::RESPONSE_FORTER_DECISION_KEY => 'approve',
            ForterResponseParser::RESPONSE_RECOMMENDATION_KEY => 'SOME_REC',
            ForterResponseParser::RESPONSE_THREE_DS_AUTH_ON_EXCLUSION_KEY => 'never',
        ]);

        $payment->expects($this->exactly(3))
            ->method('setAdditionalInformation')
            ->willReturnCallback(function (string $key, mixed $value) {
                static $callIndex = 0;
                $callIndex++;
                match ($callIndex) {
                    1 => $this->assertSame(PaymentPlaceStart::PRE_DECISION_KEY, $key),
                    2 => $this->assertSame(PaymentPlaceStart::PRE_RECOMMENDATIONS_KEY, $key),
                    3 => $this->assertSame(PaymentPlaceStart::THREE_DS_AUTH_ON_EXCLUSION_KEY, $key),
                };
                return null;
            });

        $this->observer->execute($observer);
    }

    public function testValidationExceptionIsLoggedAndSwallowed(): void
    {
        [$observer, $payment] = $this->createObserverWithPayment();
        $this->enableForter('forter-token-123');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000005');
        $payment->method('getOrder')->willReturn($order);

        $this->orderRequestBuilder->method('buildFraudDetectionPayload')->willReturn(['payload']);
        $this->tapbuyService->method('sendRequest')->willReturn(false);
        $this->responseParser->method('parse')
            ->willThrowException(new ValidationException(__('Bad response')));

        $this->logger->expects($this->once())->method('logException');

        // Should NOT throw — ValidationException is caught and logged
        $this->observer->execute($observer);
    }

    public function testRuntimeExceptionIsLoggedAndSwallowed(): void
    {
        [$observer, $payment] = $this->createObserverWithPayment();
        $this->enableForter('forter-token-123');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000006');
        $payment->method('getOrder')->willReturn($order);

        $this->orderRequestBuilder->method('buildFraudDetectionPayload')
            ->willThrowException(new \RuntimeException('Builder failed'));

        $this->logger->expects($this->once())->method('logException');

        $this->observer->execute($observer);
    }

    public function testPaymentDeclinedExceptionIsNotSwallowed(): void
    {
        [$observer, $payment] = $this->createObserverWithPayment();
        $this->enableForter('forter-token-123');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000007');
        $payment->method('getOrder')->willReturn($order);

        $this->orderRequestBuilder->method('buildFraudDetectionPayload')
            ->willThrowException(new PaymentDeclinedException(__('Declined')));

        $this->expectException(PaymentDeclinedException::class);

        $this->observer->execute($observer);
    }

    public function testEmptyRecommendationResultsInEmptyArray(): void
    {
        [$observer, $payment] = $this->createObserverWithPayment();
        $this->enableForter('forter-token-123');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000008');
        $payment->method('getOrder')->willReturn($order);

        $this->orderRequestBuilder->method('buildFraudDetectionPayload')->willReturn(['payload']);
        $this->tapbuyService->method('sendRequest')->willReturn(['raw']);
        $this->responseParser->method('parse')->willReturn([
            ForterResponseParser::RESPONSE_FORTER_DECISION_KEY => 'approve',
            // Empty recommendation → empty recommendations array
        ]);

        $payment->expects($this->exactly(3))
            ->method('setAdditionalInformation')
            ->willReturnCallback(function (string $key, mixed $value) {
                if ($key === PaymentPlaceStart::PRE_RECOMMENDATIONS_KEY) {
                    $this->assertSame([], $value);
                }
                return null;
            });

        $this->observer->execute($observer);
    }

    public function testDefaultThreeDsAuthWhenNotInResponse(): void
    {
        [$observer, $payment] = $this->createObserverWithPayment();
        $this->enableForter('forter-token-123');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100000009');
        $payment->method('getOrder')->willReturn($order);

        $this->orderRequestBuilder->method('buildFraudDetectionPayload')->willReturn(['payload']);
        $this->tapbuyService->method('sendRequest')->willReturn(['raw']);
        $this->responseParser->method('parse')->willReturn([
            ForterResponseParser::RESPONSE_FORTER_DECISION_KEY => 'approve',
        ]);

        $payment->expects($this->exactly(3))
            ->method('setAdditionalInformation')
            ->willReturnCallback(function (string $key, mixed $value) {
                if ($key === PaymentPlaceStart::THREE_DS_AUTH_ON_EXCLUSION_KEY) {
                    $this->assertSame(CheckoutDataInterface::THREE_DS_AUTH_ALWAYS, $value);
                }
                return null;
            });

        $this->observer->execute($observer);
    }

    private function enableForter(string $token): void
    {
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(true);
        $this->checkoutData->method('getForterToken')->willReturn($token);
    }
}
