<?php

/**
 * Tapbuy Forter Order Request Builder Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_Forter
 */

declare(strict_types=1);

namespace Tapbuy\Forter\Api\RequestBuilder;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Interface OrderBuilderInterface
 *
 * Provides order request building for Forter fraud detection.
 */
interface OrderBuilderInterface
{
    /**
     * Build the payload for fraud detection API call
     *
     * @param OrderInterface $order
     * @param OrderPaymentInterface $payment
     * @param string $orderStage
     * @return array
     */
    public function buildFraudDetectionPayload(
        OrderInterface $order,
        OrderPaymentInterface $payment,
        string $orderStage
    ): array;
}
