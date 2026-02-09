<?php

/**
 * Tapbuy Forter Basic Info Request Builder Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_Forter
 */

declare(strict_types=1);

namespace Tapbuy\Forter\Api\RequestBuilder;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Interface BasicInfoBuilderInterface
 *
 * Provides basic info request building for Forter fraud detection.
 */
interface BasicInfoBuilderInterface
{
    /**
     * Get connection information.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getConnectionInformation(OrderInterface $order): array;

    /**
     * Get additional identifiers.
     *
     * @param OrderInterface $order
     * @param string $orderStage
     * @return array
     */
    public function getAdditionalIdentifiers(OrderInterface $order, string $orderStage): array;
}
