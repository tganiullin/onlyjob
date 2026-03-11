<?php

namespace Tests\Feature;

use GuzzleHttp\Client as GuzzleClient;
use OpenAI\Client;
use OpenAI\Contracts\ClientContract;
use ReflectionProperty;
use Tests\TestCase;

class OpenAiClientProxyTest extends TestCase
{
    public function test_it_passes_proxy_configuration_to_openai_http_client(): void
    {
        config()->set('openai.api_key', 'test-key');
        config()->set('openai.organization', null);
        config()->set('openai.project', null);
        config()->set('openai.base_uri', null);
        config()->set('openai.request_timeout', 13);
        config()->set('openai.proxy', 'socks5://127.0.0.1:9050');

        $httpClient = $this->resolveUnderlyingHttpClient();

        $this->assertSame(13, $httpClient->getConfig('timeout'));
        $this->assertSame('socks5://127.0.0.1:9050', $httpClient->getConfig('proxy'));
    }

    public function test_it_does_not_set_proxy_when_configuration_is_empty(): void
    {
        config()->set('openai.api_key', 'test-key');
        config()->set('openai.organization', null);
        config()->set('openai.project', null);
        config()->set('openai.base_uri', null);
        config()->set('openai.request_timeout', 27);
        config()->set('openai.proxy', '  ');

        $httpClient = $this->resolveUnderlyingHttpClient();

        $this->assertSame(27, $httpClient->getConfig('timeout'));
        $this->assertNull($httpClient->getConfig('proxy'));
    }

    private function resolveUnderlyingHttpClient(): GuzzleClient
    {
        $client = $this->app->make(ClientContract::class);

        $this->assertInstanceOf(Client::class, $client);

        $transporterProperty = new ReflectionProperty($client, 'transporter');
        $transporterProperty->setAccessible(true);
        $transporter = $transporterProperty->getValue($client);

        $httpClientProperty = new ReflectionProperty($transporter, 'client');
        $httpClientProperty->setAccessible(true);
        $httpClient = $httpClientProperty->getValue($transporter);

        $this->assertInstanceOf(GuzzleClient::class, $httpClient);

        return $httpClient;
    }
}
