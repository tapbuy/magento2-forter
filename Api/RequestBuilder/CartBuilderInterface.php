<?php

/**
 * Tapbuy Forter Cart Request Builder Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_Forter
 */

declare(strict_types=1);

namespace Tapbuy\Forter\Api\RequestBuilder;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Interface CartBuilderInterface
 *
 * Provides cart request building for Forter fraud detection.
 */
interface CartBuilderInterface
{
    /**
     * Get total amount.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getTotalAmount(OrderInterface $order): array;

    /**
     * Get cart items.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getCartItems(OrderInterface $order): array;

    /**
     * Get total discount.
     *
     * @param OrderInterface $order
     * @return array|null
     */
    public function getTotalDiscount(OrderInterface $order): ?array;
}
