<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Api;

/**
 * Interface for payment method providers.
 * Implemented by PSP-specific modules (e.g., forter-adyen) to register their payment methods
 * that should be processed for Forter fraud detection and 3DS handling.
 */
interface PaymentMethodProviderInterface
{
    /**
     * Get the list of payment method codes supported by this provider.
     *
     * @return string[]
     */
    public function getPaymentMethods(): array;
}
