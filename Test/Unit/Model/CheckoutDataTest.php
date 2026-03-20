<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Test\Unit\Model;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\Forter\Api\Data\CheckoutDataInterface;
use Tapbuy\Forter\Model\CheckoutData;

class CheckoutDataTest extends TestCase
{
    private CheckoutData $checkoutData;

    protected function setUp(): void
    {
        $this->checkoutData = new CheckoutData();
    }

    public function testInitialStateHasNullToken(): void
    {
        $this->assertNull($this->checkoutData->getForterToken());
    }

    public function testInitialStateHasNullCollectedData(): void
    {
        $this->assertNull($this->checkoutData->getCollectedForterData());
    }

    public function testSetAndGetForterToken(): void
    {
        $this->checkoutData->setForterToken('test-token-123');
        $this->assertSame('test-token-123', $this->checkoutData->getForterToken());
    }

    public function testSetAndGetCollectedForterData(): void
    {
        $this->checkoutData->setCollectedForterData('{"cardBin":"123456"}');
        $this->assertSame('{"cardBin":"123456"}', $this->checkoutData->getCollectedForterData());
    }

    public function testInitFromPaymentExtractsForterToken(): void
    {
        $tapbuyInfo = json_encode(['forter_token' => 'my-forter-token']);
        $payment = $this->createPaymentWithAdditionalInfo([
            CheckoutDataInterface::TAPBUY_ADDITIONAL_INFORMATION_KEY => $tapbuyInfo,
        ]);

        $this->checkoutData->initFromPayment($payment);

        $this->assertSame('my-forter-token', $this->checkoutData->getForterToken());
    }

    public function testInitFromPaymentExtractsCollectedForterData(): void
    {
        $tapbuyInfo = json_encode([
            'forter_token' => 'token',
            'collected_forter_data' => '{"cardBin":"411111"}',
        ]);
        $payment = $this->createPaymentWithAdditionalInfo([
            CheckoutDataInterface::TAPBUY_ADDITIONAL_INFORMATION_KEY => $tapbuyInfo,
        ]);

        $this->checkoutData->initFromPayment($payment);

        $this->assertSame('{"cardBin":"411111"}', $this->checkoutData->getCollectedForterData());
    }

    public function testInitFromPaymentDoesNothingWhenNoTapbuyKey(): void
    {
        $payment = $this->createPaymentWithAdditionalInfo([
            'other_key' => 'value',
        ]);

        $this->checkoutData->initFromPayment($payment);

        $this->assertNull($this->checkoutData->getForterToken());
        $this->assertNull($this->checkoutData->getCollectedForterData());
    }

    public function testInitFromPaymentDoesNothingWhenTapbuyInfoIsInvalidJson(): void
    {
        $payment = $this->createPaymentWithAdditionalInfo([
            CheckoutDataInterface::TAPBUY_ADDITIONAL_INFORMATION_KEY => 'not-valid-json',
        ]);

        $this->checkoutData->initFromPayment($payment);

        $this->assertNull($this->checkoutData->getForterToken());
    }

    public function testInitFromPaymentDoesNothingWhenTapbuyInfoDecodesToNonArray(): void
    {
        $payment = $this->createPaymentWithAdditionalInfo([
            CheckoutDataInterface::TAPBUY_ADDITIONAL_INFORMATION_KEY => '"just a string"',
        ]);

        $this->checkoutData->initFromPayment($payment);

        $this->assertNull($this->checkoutData->getForterToken());
    }

    public function testInitFromPaymentHandlesMissingForterTokenKey(): void
    {
        $tapbuyInfo = json_encode(['other_data' => 'value']);
        $payment = $this->createPaymentWithAdditionalInfo([
            CheckoutDataInterface::TAPBUY_ADDITIONAL_INFORMATION_KEY => $tapbuyInfo,
        ]);

        $this->checkoutData->initFromPayment($payment);

        $this->assertNull($this->checkoutData->getForterToken());
    }

    private function createPaymentWithAdditionalInfo(array $additionalInfo): OrderPaymentInterface&MockObject
    {
        $payment = $this->createMock(OrderPaymentInterface::class);
        $payment->method('getAdditionalInformation')->willReturn($additionalInfo);
        return $payment;
    }
}
