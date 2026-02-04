<?php

/**
 * Tapbuy Forter Payment Request Builder Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_Forter
 */

declare(strict_types=1);

namespace Tapbuy\Forter\Api\RequestBuilder;

use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Interface PaymentBuilderInterface
 *
 * Provides payment request building for Forter fraud detection.
 */
interface PaymentBuilderInterface
{
    /**
     * Get payment data.
     *
     * @param OrderInterface $order
     * @param OrderPaymentInterface $payment
     * @return array
     * @throws InvalidArgumentException
     */
    public function getPaymentData(OrderInterface $order, OrderPaymentInterface $payment): array;
}
