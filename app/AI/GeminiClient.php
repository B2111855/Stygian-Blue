<?php

namespace App\AI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class GeminiClient
{
    private Client $httpClient;
    private string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, string $model = 'models/gemini-2.5-flash')
    {
        // Resolve API key in order of precedence:
        // 1. Explicit constructor argument
        // 2. GEMINI_API_KEY (env array or getenv)
        // 3. GOOGLE_API_KEY (unified key for Google services)
        $resolvedKey = $apiKey
            ?? ($_ENV['GEMINI_API_KEY'] ?? null)
            ?? ($_ENV['GOOGLE_API_KEY'] ?? null)
            ?? getenv('GEMINI_API_KEY')
            ?? getenv('GOOGLE_API_KEY')
            ?? '';

        if ($resolvedKey === '') {
            throw new RuntimeException('Gemini API key is missing. Set GOOGLE_API_KEY or GEMINI_API_KEY in your environment.');
        }

        $this->apiKey = $resolvedKey;
        $this->model  = $model;
        $this->httpClient = new Client([
            'base_uri'    => 'https://generativelanguage.googleapis.com/v1beta/',
            'timeout'     => 20,
            'http_errors' => false,
        ]);
    }

    /**
     * @param array<int, array{role:string, content:string}> $messages
     * @param string|null $systemInstruction
     * @return array{text:string, raw:array}
     */
    public function generate(array $messages, ?string $systemInstruction = null): array
    {
        if (empty($messages)) {
            throw new RuntimeException('Conversation history is empty.');
        }

        $contents = [];
        foreach ($messages as $message) {
            $text = trim($message['content'] ?? '');
            if ($text === '') {
                continue;
            }

            $role = $this->mapRole($message['role'] ?? 'user');
            $contents[] = [
                'role'  => $role,
                'parts' => [
                    ['text' => $text],
                ],
            ];
        }

        if (empty($contents)) {
            throw new RuntimeException('No valid messages to send to Gemini.');
        }

        $payload = [
            'contents' => $contents,
        ];

        if ($systemInstruction !== null && $systemInstruction !== '') {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ];
        }

        try {
            $response = $this->httpClient->post($this->model . ':generateContent', [
                'query' => ['key' => $this->apiKey],
                'json'  => $payload,
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Gemini API request failed: ' . $exception->getMessage(), 0, $exception);
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if ($statusCode >= 400) {
            $apiMessage = is_array($data)
                ? ($data['error']['message'] ?? json_encode($data, JSON_UNESCAPED_UNICODE))
                : $body;
            throw new RuntimeException('Gemini API (HTTP ' . $statusCode . '): ' . $apiMessage);
        }

        if (!is_array($data)) {
            throw new RuntimeException('Unexpected response from Gemini API.');
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text === '') {
            throw new RuntimeException('Gemini API returned an empty response.');
        }

        return [
            'text' => $text,
            'raw'  => $data,
        ];
    }

    private function mapRole(string $role): string
    {
        $normalized = strtolower($role);
        return $normalized === 'assistant' ? 'model' : 'user';
    }
}
