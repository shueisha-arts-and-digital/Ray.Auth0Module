<?php

declare(strict_types=1);

namespace Ray\Auth0Module\Cache;

use DateInterval;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

final class ArrayCacheItem implements CacheItemInterface
{
    public function __construct(
        private string $key,
        private bool $hit,
        private mixed $value
    ) {
    }

    public function getKey() : string
    {
        return $this->key;
    }

    public function get() : mixed
    {
        return $this->value;
    }

    public function isHit() : bool
    {
        return $this->hit;
    }

    public function set(mixed $value) : static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration) : static
    {
        return $this;
    }

    public function expiresAfter(DateInterval|int|null $time) : static
    {
        return $this;
    }
}
