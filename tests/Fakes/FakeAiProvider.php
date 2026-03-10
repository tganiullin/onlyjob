<?php

namespace Tests\Fakes;

use App\AI\Contracts\AiProvider;
use App\AI\Data\AiRequest;
use App\AI\Data\AiStructuredResponse;
use RuntimeException;

class FakeAiProvider implements AiProvider
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $responses = [];

    public int $callCount = 0;

    /**
     * @var list<AiRequest>
     */
    public array $requests = [];

    /**
     * @param  list<array<string, mixed>>  $responses
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    public function generateStructured(AiRequest $request): AiStructuredResponse
    {
        $this->requests[] = $request;

        $response = $this->responses[$this->callCount] ?? null;

        if (! is_array($response)) {
            throw new RuntimeException('Fake AI provider has no response for current call.');
        }

        $this->callCount++;

        return new AiStructuredResponse(
            content: $response,
            rawResponse: json_encode($response, JSON_THROW_ON_ERROR),
        );
    }
}
