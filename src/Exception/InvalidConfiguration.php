<?php

declare(strict_types=1);

namespace Ray\Auth0Module\Exception;

/**
 * モジュールに渡された config の値が不正な場合に投げる例外
 */
class InvalidConfiguration extends \InvalidArgumentException
{
}
