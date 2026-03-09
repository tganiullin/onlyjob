<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiProvider;
use App\AI\Data\AiRequest;
use App\AI\Data\AiStructuredResponse;
use App\AI\Exceptions\AiProviderException;
use OpenAI\Contracts\ClientContract;
use Throwable;

final class OpenAiProvider implements AiProvider
{
    public function __construct(
        public ClientContract $client,
    ) {}

    public function generateStructured(AiRequest $request): AiStructuredResponse
    {
        try {
            $response = $this->client->responses()->create($this->buildResponsesPayload($request));

            if ($response->status !== 'completed') {
                $errorMessage = $response->error?->message ?? sprintf(
                    'OpenAI response generation finished with status "%s".',
                    $response->status,
                );

                throw new \RuntimeException($errorMessage);
            }

            $content = $response->outputText;

            if (! is_string($content) || trim($content) === '') {
                throw new \RuntimeException('OpenAI returned an empty response content.');
            }

            $decodedContent = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decodedContent)) {
                throw new \RuntimeException('OpenAI response content must be a JSON object.');
            }

            return new AiStructuredResponse(content: $decodedContent, rawResponse: $content);
        } catch (Throwable $exception) {
            throw AiProviderException::requestFailed('openai', $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponsesPayload(AiRequest $request): array
    {
        $payload = [
            'model' => $request->model ?? (string) config('ai.openai.model', 'gpt-4o-mini'),
            'instructions' => $request->systemPrompt,
            'input' => $request->userPrompt,
            'temperature' => $request->temperature ?? (float) config('ai.openai.temperature', 0.1),
        ];

        if ($request->maxTokens !== null) {
            $payload['max_output_tokens'] = $request->maxTokens;
        }

        if ($request->jsonSchema !== null) {
            $payload['text'] = [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $request->schemaName ?? 'structured_output',
                    'schema' => $request->jsonSchema,
                    'strict' => true,
                ],
            ];
        }

        return $payload;
    }
}
