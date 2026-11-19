<?php

declare(strict_types=1);

namespace Ray\Auth0Module\Provider;

use Psr\Http\Client\ClientInterface;
use Ray\Auth0Module\Http\FakeHttpClient;

/**
 * HTTP クライアントを FakeHttpClient に差し替えた ManagementClientProvider
 */
final class SpyManagementClientProvider extends ManagementClientProvider
{
    public FakeHttpClient $client;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->client = new FakeHttpClient();
    }

    protected function httpClient() : ClientInterface
    {
        return $this->client;
    }
}
