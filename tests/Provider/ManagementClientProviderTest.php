<?php

declare(strict_types=1);

namespace Ray\Auth0Module\Provider;

use Auth0\SDK\API\Management\Management;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ray\Auth0Module\Cache\ArrayCachePool;
use Ray\Auth0Module\Exception\InvalidConfiguration;
use stdClass;

class ManagementClientProviderTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $config = [
        'domain' => 'https://example.auth0.com/',
        'clientId' => 'AUTH0_CLIENT_ID',
        'clientSecret' => 'AUTH0_CLIENT_SECRET',
        'audience' => ['https://api.example.com'],
        'customDomain' => 'login.example.com',
    ];

    public function testGetReturnsManagement() : void
    {
        $this->assertInstanceOf(Management::class, (new ManagementClientProvider($this->config))->get());
    }

    public function testRequestsUseNormalizedHostAndDefaultAudience() : void
    {
        $provider = new SpyManagementClientProvider($this->config);
        $provider->get()->users->get('auth0|1');

        $requests = $provider->client->requests;
        $this->assertCount(2, $requests);

        // 1 件目はトークン要求。audience は config の値ではなく SDK 既定の Management API 用になる
        $this->assertSame('POST', $requests[0]->getMethod());
        $this->assertSame('https://example.auth0.com/oauth/token', (string) $requests[0]->getUri());
        $body = json_decode((string) $requests[0]->getBody(), true);
        $this->assertIsArray($body);
        $this->assertSame('https://example.auth0.com/api/v2/', $body['audience']);
        $this->assertSame('AUTH0_CLIENT_ID', $body['client_id']);
        $this->assertSame('AUTH0_CLIENT_SECRET', $body['client_secret']);

        // 2 件目は Management API 呼び出し。customDomain ではなく正規化した domain に出る
        $this->assertStringStartsWith('https://example.auth0.com/api/v2/users/', (string) $requests[1]->getUri());
        $this->assertSame('Bearer FAKE_TOKEN', $requests[1]->getHeaderLine('Authorization'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function domainProvider() : array
    {
        return [
            'スキーム付き' => ['https://example.auth0.com'],
            'スキームなし' => ['example.auth0.com'],
            '末尾スラッシュ付き' => ['example.auth0.com/'],
            'スキームと末尾スラッシュ付き' => ['https://example.auth0.com/'],
        ];
    }

    #[DataProvider('domainProvider')]
    public function testDomainIsNormalized(string $domain) : void
    {
        $provider = new SpyManagementClientProvider(['domain' => $domain] + $this->config);
        $provider->get()->users->get('auth0|1');

        $this->assertSame('example.auth0.com', $provider->client->requests[0]->getUri()->getHost());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidConfigProvider() : array
    {
        $base = ['domain' => 'example.auth0.com', 'clientId' => 'ID', 'clientSecret' => 'SECRET'];

        return [
            'domain なし' => [['domain' => null] + $base],
            'domain が空文字' => [['domain' => ''] + $base],
            'domain からホスト名を取り出せない' => [['domain' => 'https://'] + $base],
            'domain が文字列でない' => [['domain' => 123] + $base],
            'clientId なし' => [['clientId' => null] + $base],
            'clientSecret なし' => [['clientSecret' => null] + $base],
            'clientSecret が空文字' => [['clientSecret' => ''] + $base],
            'tokenCache が PSR-6 でない' => [['tokenCache' => new stdClass()] + $base],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigProvider')]
    public function testInvalidConfigThrows(array $config) : void
    {
        $this->expectException(InvalidConfiguration::class);
        new ManagementClientProvider($config);
    }

    public function testTokenIsSavedToCache() : void
    {
        $cache = new ArrayCachePool();
        $provider = new SpyManagementClientProvider(['tokenCache' => $cache] + $this->config);
        $provider->get()->users->get('auth0|1');

        $this->assertContains('FAKE_TOKEN', $cache->values);
    }

    public function testCachedTokenSkipsTokenRequest() : void
    {
        $cache = new ArrayCachePool();
        $cache->values['auth0_management_token_AUTH0_CLIENT_ID'] = 'CACHED_TOKEN';
        $provider = new SpyManagementClientProvider(['tokenCache' => $cache] + $this->config);
        $provider->get()->users->get('auth0|1');

        $requests = $provider->client->requests;
        $this->assertCount(1, $requests);
        $this->assertSame('/api/v2/users/auth0%7C1', $requests[0]->getUri()->getPath());
        $this->assertSame('Bearer CACHED_TOKEN', $requests[0]->getHeaderLine('Authorization'));
    }
}
