<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Tapbuy\Forter\Api\PaymentMethodProviderInterface;
use Tapbuy\Forter\Model\CompositePaymentMethodProvider;

class CompositePaymentMethodProviderTest extends TestCase
{
    public function testGetPaymentMethodsReturnsEmptyArrayWithNoProviders(): void
    {
        $composite = new CompositePaymentMethodProvider([]);
        $this->assertSame([], $composite->getPaymentMethods());
    }

    public function testGetPaymentMethodsAggregatesFromSingleProvider(): void
    {
        $provider = $this->createMock(PaymentMethodProviderInterface::class);
        $provider->method('getPaymentMethods')->willReturn(['adyen_cc']);

        $composite = new CompositePaymentMethodProvider([$provider]);
        $this->assertSame(['adyen_cc'], $composite->getPaymentMethods());
    }

    public function testGetPaymentMethodsAggregatesFromMultipleProviders(): void
    {
        $provider1 = $this->createMock(PaymentMethodProviderInterface::class);
        $provider1->method('getPaymentMethods')->willReturn(['adyen_cc']);

        $provider2 = $this->createMock(PaymentMethodProviderInterface::class);
        $provider2->method('getPaymentMethods')->willReturn(['stripe_cc']);

        $composite = new CompositePaymentMethodProvider([$provider1, $provider2]);
        $result = $composite->getPaymentMethods();

        $this->assertContains('adyen_cc', $result);
        $this->assertContains('stripe_cc', $result);
    }

    public function testGetPaymentMethodsDeduplicates(): void
    {
        $provider1 = $this->createMock(PaymentMethodProviderInterface::class);
        $provider1->method('getPaymentMethods')->willReturn(['adyen_cc']);

        $provider2 = $this->createMock(PaymentMethodProviderInterface::class);
        $provider2->method('getPaymentMethods')->willReturn(['adyen_cc', 'stripe_cc']);

        $composite = new CompositePaymentMethodProvider([$provider1, $provider2]);
        $result = $composite->getPaymentMethods();

        $this->assertCount(2, $result);
    }

    public function testGetPaymentMethodsSkipsNonProviderInstances(): void
    {
        $validProvider = $this->createMock(PaymentMethodProviderInterface::class);
        $validProvider->method('getPaymentMethods')->willReturn(['adyen_cc']);

        $notAProvider = new \stdClass();

        $composite = new CompositePaymentMethodProvider([$validProvider, $notAProvider]);
        $this->assertSame(['adyen_cc'], $composite->getPaymentMethods());
    }
}
