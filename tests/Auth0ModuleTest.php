<?php

declare(strict_types=1);

namespace Ray\Auth0Module;

use Auth0\SDK\API\Authentication;
use Auth0\SDK\API\Management\Management;
use PHPUnit\Framework\TestCase;
use Ray\Auth0Module\Auth\AuthInterface;
use Ray\Auth0Module\Cache\ArrayCachePool;
use Ray\Di\Injector;

class Auth0ModuleTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $config = [
        'domain' => 'https://example.com',
        'clientId' => 'AUTH0_CLIENT_ID',
        'clientSecret' => 'AUTH0_CLIENT_SECRET',
        'audience' => ['https://api.example.com'],
    ];

    public function testModule() : void
    {
        $injector = new Injector(new Auth0Module($this->config), dirname(__DIR__) . '/tmp');

        $this->assertInstanceOf(Authentication::class, $injector->getInstance(Authentication::class));
        $this->assertInstanceOf(Management::class, $injector->getInstance(Management::class));
        $this->assertInstanceOf(AuthInterface::class, $injector->getInstance(AuthInterface::class));
    }

    public function testManagementIsSingleton() : void
    {
        $injector = new Injector(new Auth0Module($this->config), dirname(__DIR__) . '/tmp');

        $this->assertSame($injector->getInstance(Management::class), $injector->getInstance(Management::class));
    }

    public function testModuleWithTokenCache() : void
    {
        // tokenCache は Auth / Authentication 側の SdkConfiguration にも渡るが、そのまま生成できること
        $injector = new Injector(new Auth0Module(['tokenCache' => new ArrayCachePool()] + $this->config), dirname(__DIR__) . '/tmp');

        $this->assertInstanceOf(Authentication::class, $injector->getInstance(Authentication::class));
        $this->assertInstanceOf(Management::class, $injector->getInstance(Management::class));
        $this->assertInstanceOf(AuthInterface::class, $injector->getInstance(AuthInterface::class));
    }
}
