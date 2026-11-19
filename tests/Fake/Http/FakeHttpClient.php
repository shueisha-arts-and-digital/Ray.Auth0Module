<?php

declare(strict_types=1);

namespace Ray\Auth0Module\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 送信された要求を記録し、URL のパスに応じた固定のレスポンスを返す PSR-18 クライアント
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    public function sendRequest(RequestInterface $request) : ResponseInterface
    {
        $this->requests[] = $request;

        if ($request->getUri()->getPath() === '/oauth/token') {
            return new Response(200, ['Content-Type' => 'application/json'], '{"access_token":"FAKE_TOKEN","expires_in":86400}');
        }

        return new Response(200, ['Content-Type' => 'application/json'], '{"user_id":"auth0|1"}');
    }
}
