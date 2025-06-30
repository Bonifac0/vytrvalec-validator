<?php
namespace bonifac0\OllamaClient;

use GuzzleHttp\Client;

class OllamaClient
{
    # custom writen chating client
    private Client $client;

    /**
     * @param string $baseUri Base URL of the Ollama server
     * @param array $auth Optional auth array: ['username', 'password', 'digest']
     */
    public function __construct(string $baseUri, array $auth = [])
    {
        $options = [
            'base_uri' => rtrim($baseUri, '/') . '/',
        ];

        if (!empty($auth)) {
            $auth[] = 'digest';
            $options['auth'] = $auth;
        }

        $this->client = new Client($options);
    }

    /**
     * Sends a chat payload to the Ollama /api/chat endpoint and returns the parsed JSON response.
     *
     * Automatically sets 'stream' to false to ensure a single full response is returned.
     * The function assumes that the model response contains a JSON string in the 'message.content' field,
     * which it will decode and return as an associative array.
     *
     * Example payload:
     * [
     *     'model' => 'gemma3:27b',
     *     'messages' => [
     *         [
     *             'role' => 'user',
     *             'content' => 'Co je na obrázku?',
     *             'images' => [$image] // base64-encoded image
     *         ]
     *     ]
     * ]
     *
     * @param array $payload Payload compatible with /api/chat (e.g. model, messages, etc.).
     * @return array|null Parsed content returned by the assistant, or null if decoding fails.
     */
    public function chat(array $payload): array
    {
        // Ensure response is returned in full (non-streaming)
        $payload['stream'] = false;

        $response = $this->client->post('/api/chat', [
            'json' => $payload,
        ]);

        $raw = json_decode((string) $response->getBody(), true);
        return json_decode($raw['message']['content'] ?? '{}', true);
    }
}
