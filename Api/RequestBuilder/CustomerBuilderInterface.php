<?php

/**
 * Tapbuy Forter Customer Request Builder Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_Forter
 */

declare(strict_types=1);

namespace Tapbuy\Forter\Api\RequestBuilder;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Interface CustomerBuilderInterface
 *
 * Provides customer request building for Forter fraud detection.
 */
interface CustomerBuilderInterface
{
    /**
     * Get primary delivery details.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getPrimaryDeliveryDetails(OrderInterface $order): array;

    /**
     * Get primary recipient.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getPrimaryRecipient(OrderInterface $order): array;

    /**
     * Get billing details.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getBillingDetails(OrderInterface $order): array;

    /**
     * Get account owner info.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getAccountOwnerInfo(OrderInterface $order): array;

    /**
     * Get customer account data.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getCustomerAccountData(OrderInterface $order): array;
}
