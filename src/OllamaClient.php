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
     * Send a generic payload to Ollama /api/chat and return the decoded response.
     *
     * @param array $payload Payload compatible with /api/chat (e.g. model, messages, stream...)
     * @return array|null Response decoded as array, or null on error
     */
    public function chat(array $payload): array
    {
        // payload example
        // $payload = [
        //     'model' => 'gemma3:27b',
        //     'messages' => [
        //         [
        //             'role' => 'user',
        //             'content' => 'Co je na obrázku?',
        //             'images' => [$image]
        //         ]
        //     ],
        //     'stream' => false
        // ];

        $payload['stream'] = false; # it respond in one

        $response = $this->client->post('/api/chat', [
            'json' => $payload,
        ]);
        $output = json_decode(json_decode((string) $response->getBody(), true)['message']['content'], true);

        return $output;
    }
}
