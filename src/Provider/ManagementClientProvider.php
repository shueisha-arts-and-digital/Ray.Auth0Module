<?php

declare(strict_types=1);

namespace Ray\Auth0Module\Provider;

use Auth0\SDK\API\Management\Management;
use Auth0\SDK\API\Management\Wrapper\ManagementClient;
use Auth0\SDK\API\Management\Wrapper\ManagementClientOptions;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Ray\Auth0Module\Annotation\Auth0Config;
use Ray\Auth0Module\Exception\InvalidConfiguration;
use Ray\Di\ProviderInterface;

/**
 * @implements ProviderInterface<Management>
 */
class ManagementClientProvider implements ProviderInterface
{
    private string $domain;
    private string $clientId;
    private string $clientSecret;
    private ?CacheItemPoolInterface $tokenCache;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        #[Auth0Config('config')] array $config
    ) {
        // Management API が読むのは domain / clientId / clientSecret / tokenCache のみ。
        // audience と customDomain は渡さず、audience は SDK 既定の https://{domain}/api/v2/ に任せる。
        $this->domain = self::normalizeDomain(self::requireString($config, 'domain'));
        $this->clientId = self::requireString($config, 'clientId');
        $this->clientSecret = self::requireString($config, 'clientSecret');
        $this->tokenCache = self::optionalTokenCache($config);
    }

    public function get() : Management
    {
        $client = new ManagementClient(new ManagementClientOptions(
            domain: $this->domain,
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            httpClient: $this->httpClient(),
            tokenCache: $this->tokenCache,
        ));

        return $client->getManagement();
    }

    /**
     * Management API とトークン取得に使う PSR-18 クライアント
     *
     * null の場合は SDK が自動検出したクライアントを使う。テストで要求を捕捉するための差し替え口。
     */
    protected function httpClient() : ?ClientInterface
    {
        return null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function requireString(array $config, string $key) : string
    {
        $value = $config[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidConfiguration(sprintf('Auth0 config "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function optionalTokenCache(array $config) : ?CacheItemPoolInterface
    {
        $value = $config['tokenCache'] ?? null;
        if ($value === null || $value instanceof CacheItemPoolInterface) {
            return $value;
        }

        throw new InvalidConfiguration(sprintf('Auth0 config "tokenCache" must be an instance of %s.', CacheItemPoolInterface::class));
    }

    /**
     * スキーム付き・なし・末尾スラッシュ付きのいずれもホスト名へ正規化する
     *
     * 9.x の Wrapper は domain をそのまま "https://{domain}/api/v2" に埋め込むため、
     * 8.x の SdkConfiguration と同様にホスト名だけを取り出す。
     */
    private static function normalizeDomain(string $domain) : string
    {
        $trimmed = trim($domain);
        $url = preg_match('#^[a-z][a-z0-9+.-]*://#i', $trimmed) === 1 ? $trimmed : 'https://' . $trimmed;
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            throw new InvalidConfiguration(sprintf('Auth0 config "domain" is not a valid host name: "%s".', $domain));
        }

        return $host;
    }
}
