<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * Exception thrown when payment is declined by fraud detection.
 */
class PaymentDeclinedException extends LocalizedException
{
}
