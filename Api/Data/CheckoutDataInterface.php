<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Api\Data;

use Magento\Sales\Api\Data\OrderPaymentInterface;

interface CheckoutDataInterface
{
    public const TAPBUY_ADDITIONAL_INFORMATION_KEY = 'tapbuy';
    public const FORTER_TOKEN_KEY = 'forter_token';

    public const COLLECTED_FORTER_DATA_KEY = 'collected_forter_data';

    public const CARD_BRAND_KEY = 'cardBrand';
    public const CARD_BIN_KEY = 'cardBin';
    public const CARD_LAST_4_DIGITS_KEY = 'cardLast4Digits';
    public const CARD_HOLDER_NAME_KEY = 'cardHolderName';

    // Fraud decision actions from tapbuy-api
    public const ACTION_APPROVE = 'approve';
    public const ACTION_DECLINE = 'decline';

    // 3DS authentication options
    public const THREE_DS_AUTH_ALWAYS = 'always';
    public const THREE_DS_AUTH_NEVER = 'never';

    // Response keys from tapbuy-api fraud detection
    public const THREE_DS_AUTH_ON_EXCLUSION_KEY = 'threeDsAuthOnExclusion';

    /**
     * Initialize Forter data from payment additional information.
     *
     * @param OrderPaymentInterface $payment
     * @return void
     */
    public function initFromPayment(OrderPaymentInterface $payment): void;

    /**
     * Set Forter token.
     *
     * @param string $forterToken
     * @return void
     */
    public function setForterToken(string $forterToken): void;

    /**
     * Get Forter token.
     *
     * @return string|null
     */
    public function getForterToken(): ?string;

    /**
     * Set collected Forter data.
     *
     * @param string $collectedForterData
     * @return void
     */
    public function setCollectedForterData(string $collectedForterData): void;

    /**
     * Get collected Forter data.
     *
     * @return string|null
     */
    public function getCollectedForterData(): ?string;

    /**
     * Set 3DS authentication on exclusion setting.
     *
     * @param string $threeDsAuthOnExclusion
     * @return void
     */
    public function setThreeDsAuthOnExclusion(string $threeDsAuthOnExclusion): void;

    /**
     * Get 3DS authentication on exclusion setting.
     *
     * @return string
     */
    public function getThreeDsAuthOnExclusion(): string;
}
