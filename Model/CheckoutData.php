<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Model;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use Tapbuy\Forter\Api\Data\CheckoutDataInterface;

/**
 * Holds Forter checkout data, loaded from payment additional information.
 */
class CheckoutData implements CheckoutDataInterface
{
    private ?string $forterToken = null;

    private ?string $collectedForterData = null;

    private string $threeDsAuthOnExclusion = self::THREE_DS_AUTH_ALWAYS;

    /**
     * Set the payment to read Forter data from.
     *
     * @param OrderPaymentInterface $payment
     * @return void
     */
    public function initFromPayment(OrderPaymentInterface $payment): void
    {
        $additionalInfo = $payment->getAdditionalInformation();

        if (isset($additionalInfo[self::TAPBUY_ADDITIONAL_INFORMATION_KEY])) {
            $tapbuyInfo = json_decode($additionalInfo[self::TAPBUY_ADDITIONAL_INFORMATION_KEY], true) ?? null;
            if (is_array($tapbuyInfo)) {
                if (isset($tapbuyInfo[self::FORTER_TOKEN_KEY])) {
                    $this->setForterToken($tapbuyInfo[self::FORTER_TOKEN_KEY]);
                }

                if (isset($tapbuyInfo[self::COLLECTED_FORTER_DATA_KEY])) {
                    $this->setCollectedForterData($tapbuyInfo[self::COLLECTED_FORTER_DATA_KEY]);
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function setForterToken(string $forterToken): void
    {
        $this->forterToken = $forterToken;
    }

    /**
     * @inheritDoc
     */
    public function getForterToken(): ?string
    {
        return $this->forterToken;
    }

    /**
     * @inheritDoc
     */
    public function setCollectedForterData(string $collectedForterData): void
    {
        $this->collectedForterData = $collectedForterData;
    }

    /**
     * @inheritDoc
     */
    public function getCollectedForterData(): ?string
    {
        return $this->collectedForterData;
    }

    /**
     * @inheritDoc
     */
    public function setThreeDsAuthOnExclusion(string $threeDsAuthOnExclusion): void
    {
        $this->threeDsAuthOnExclusion = $threeDsAuthOnExclusion;
    }

    /**
     * @inheritDoc
     */
    public function getThreeDsAuthOnExclusion(): string
    {
        return $this->threeDsAuthOnExclusion;
    }
}
