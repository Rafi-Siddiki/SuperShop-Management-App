<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule failure whose message is safe to show to the user
 * (out of stock, coupon limit reached, order already shipped, …).
 * Throwing it inside a DB transaction rolls the whole operation back.
 */
class ShopException extends RuntimeException
{
}
