<?php

declare(strict_types=1);

namespace Ray\Auth0Module\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * テスト用の最小限のメモリ上 PSR-6 プール（有効期限は扱わない）
 */
final class ArrayCachePool implements CacheItemPoolInterface
{
    /** @var array<string, mixed> */
    public array $values = [];

    public function getItem(string $key) : CacheItemInterface
    {
        return new ArrayCacheItem($key, array_key_exists($key, $this->values), $this->values[$key] ?? null);
    }

    /**
     * @param array<string> $keys
     *
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []) : iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    public function hasItem(string $key) : bool
    {
        return array_key_exists($key, $this->values);
    }

    public function clear() : bool
    {
        $this->values = [];

        return true;
    }

    public function deleteItem(string $key) : bool
    {
        unset($this->values[$key]);

        return true;
    }

    /**
     * @param array<string> $keys
     */
    public function deleteItems(array $keys) : bool
    {
        foreach ($keys as $key) {
            unset($this->values[$key]);
        }

        return true;
    }

    public function save(CacheItemInterface $item) : bool
    {
        $this->values[$item->getKey()] = $item->get();

        return true;
    }

    public function saveDeferred(CacheItemInterface $item) : bool
    {
        return $this->save($item);
    }

    public function commit() : bool
    {
        return true;
    }
}
