<?php

declare(strict_types=1);

/**
 * Forter API Response Parser
 *
 * Validates and unpacks Forter fraud-detection responses from the Tapbuy API.
 * The API wraps Forter data in an envelope: {success: true, data: {...}}.
 *
 * @category  Tapbuy
 * @package   Tapbuy_Forter
 */

namespace Tapbuy\Forter\Model;

use Magento\Framework\Validation\ValidationException;

class ForterResponseParser
{
    public const RESPONSE_DATA_KEY = 'data';
    public const RESPONSE_STATUS_KEY = 'status';
    public const RESPONSE_FORTER_DECISION_KEY = 'forterDecision';
    public const RESPONSE_RECOMMENDATION_KEY = 'recommendation';
    public const RESPONSE_THREE_DS_AUTH_ON_EXCLUSION_KEY = 'threeDsAuthOnExclusion';

    /**
     * Validate a Forter API response and return its inner data payload.
     *
     * The Tapbuy API wraps Forter responses as {success: true, data: {...}}.
     * This method validates the outer envelope and returns the inner data array.
     *
     * @param mixed $response Raw response from TapbuyServiceInterface::sendRequest()
     * @return array The validated data sub-array from the response envelope
     * @throws ValidationException If the response is absent, malformed, or missing required fields
     */
    public function validate(mixed $response): array
    {
        if ($response === false || $response === null) {
            throw new ValidationException(__('No response received from fraud detection service.'));
        }

        if (!is_array($response)) {
            throw new ValidationException(__('Invalid response type from fraud detection service.'));
        }

        if (!array_key_exists(self::RESPONSE_DATA_KEY, $response) || !is_array($response[self::RESPONSE_DATA_KEY])) {
            throw new ValidationException(__('Unexpected response structure: ' . json_encode($response)));
        }

        $data = $response[self::RESPONSE_DATA_KEY];
        if (!array_key_exists(self::RESPONSE_FORTER_DECISION_KEY, $data)) {
            throw new ValidationException(__('Missing forterDecision in response: ' . json_encode($response)));
        }

        return $data;
    }
}
