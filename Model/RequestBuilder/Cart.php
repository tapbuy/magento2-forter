<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Model\RequestBuilder;

use Magento\Sales\Api\Data\OrderInterface;
use Tapbuy\Forter\Api\RequestBuilder\CartBuilderInterface;

class Cart implements CartBuilderInterface
{
    /**
     * Get total amount.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getTotalAmount(OrderInterface $order): array
    {
        return [
            'orderTotal' => [
                'grossPrice' => $order->getGrandTotal()
            ]
        ];
    }

    /**
     * Get cart items.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getCartItems(OrderInterface $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'name' => $item->getName(),
                'qty' => (int) $item->getQtyOrdered(),
                'price' => $item->getPrice(),
                'productId' => $item->getProductId(),
                'sku' => $item->getSku()
            ];
        }
        return $items;
    }

    /**
     * Get total discount.
     *
     * @param OrderInterface $order
     * @return array|null
     */
    public function getTotalDiscount(OrderInterface $order): ?array
    {
        $discountAmount = $order->getDiscountAmount();

        if ($discountAmount === null || (float) $discountAmount == 0) {
            return null;
        }

        return [
            'couponCodeUsed' => $order->getCouponCode(),
            'discountAmount' => [
                'amountLocalCurrency' => (string) abs((float) $discountAmount),
                'currency' => $order->getOrderCurrencyCode()
            ]
        ];
    }
}
