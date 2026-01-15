<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Model\RequestBuilder;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Tapbuy\Forter\Api\Data\CheckoutDataInterface;

class BasicInfo
{
    private const MAX_HEADER_LENGTH = 4000;

    /**
     * @param CheckoutDataInterface $checkoutData
     * @param RemoteAddress $remote
     */
    public function __construct(
        private readonly CheckoutDataInterface $checkoutData,
        private readonly RemoteAddress $remote
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
        $headers = getallheaders();
        if (!is_array($headers)) {
            $headers = [];
        }

        $userAgent = $this->getUserAgent($headers);
        if (str_contains($userAgent, 'CyberSource')) {
            return [];
        }

        return [
            'customerIP' => $this->getRemoteIp($order),
            'userAgent' => $this->getUserAgent($headers),
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
     * @param array $headers
     * @return string
     */
    private function getUserAgent(array $headers): string
    {
        $userAgent = '';

        $userAgentKey = '';
        if (array_key_exists('User-Agent', $headers)) {
            $userAgentKey = 'User-Agent';
        } elseif (array_key_exists('user-agent', $headers)) {
            $userAgentKey = 'user-agent';
        }

        if ($userAgentKey !== '') {
            $userAgent = substr($headers[$userAgentKey], 0, self::MAX_HEADER_LENGTH);
        }

        return $userAgent;
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
