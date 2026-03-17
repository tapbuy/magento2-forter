<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Model\RequestBuilder;

use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Tapbuy\Forter\Api\Data\CheckoutDataInterface;
use Tapbuy\Forter\Api\RequestBuilder\BasicInfoBuilderInterface;

class BasicInfo implements BasicInfoBuilderInterface
{
    private const MAX_HEADER_LENGTH = 4000;

    /**
     * @param CheckoutDataInterface $checkoutData
     * @param RemoteAddress $remote
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly CheckoutDataInterface $checkoutData,
        private readonly RemoteAddress $remote,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Get connection information.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getConnectionInformation(OrderInterface $order): array
    {
        $userAgent = $this->getUserAgent();
        if (str_contains($userAgent, 'CyberSource')) {
            return [];
        }

        return [
            'customerIP' => $this->getRemoteIp($order),
            'userAgent' => $userAgent,
            'fraudDetectionCookie' => $this->checkoutData->getForterToken(),
            'merchantDeviceIdentifier' => null
        ];
    }

    /**
     * Get additional identifiers.
     *
     * @param OrderInterface $order
     * @param string $orderStage
     * @return array
     */
    public function getAdditionalIdentifiers(OrderInterface $order, string $orderStage): array
    {
        return [
            'merchant' => [
                'merchantDomain' => $order->getStore()->getUrl(),
                'merchantName' => $order->getStore()->getName()
            ],
            'magentoAdditionalOrderData' => [
                'magentoOrderStage' => $orderStage
            ]
        ];
    }

    /**
     * Get user agent.
     *
     * @return string
     */
    private function getUserAgent(): string
    {
        $userAgent = $this->request->getHeader('User-Agent');

        if ($userAgent === false || $userAgent === null) {
            return '';
        }

        return substr((string) $userAgent, 0, self::MAX_HEADER_LENGTH);
    }

    /**
     * Get remote IP address.
     *
     * @param OrderInterface $order
     * @return string
     */
    private function getRemoteIp(OrderInterface $order): string
    {
        $remoteIp = $order->getRemoteIp();
        if ($remoteIp !== null) {
            return $remoteIp;
        }

        $remoteIp = $this->remote->getRemoteAddress();
        if ($remoteIp !== false) {
            return $remoteIp;
        }

        return '';
    }
}
