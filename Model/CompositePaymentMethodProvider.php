<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Model;

use Tapbuy\Forter\Api\PaymentMethodProviderInterface;

/**
 * Composite payment method provider that aggregates payment methods from multiple PSP modules.
 * PSP-specific modules (e.g., forter-adyen, forter-stripe) inject their providers via DI.
 */
class CompositePaymentMethodProvider implements PaymentMethodProviderInterface
{
    /**
     * @param PaymentMethodProviderInterface[] $providers
     */
    public function __construct(
        private readonly array $providers = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getPaymentMethods(): array
    {
        $methods = [];

        foreach ($this->providers as $provider) {
            if ($provider instanceof PaymentMethodProviderInterface) {
                $methods = array_merge($methods, $provider->getPaymentMethods());
            }
        }

        return array_unique($methods);
    }
}
