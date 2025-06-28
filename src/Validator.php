<?php
namespace bonifac0\VytrvalecValidator;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;

class Validator
{
    private HttpClient $client;
    private string $errorLogPath;
    private array $rules;
    private array $outputSchema;

    public function __construct(
        string $credFile = "credentials.txt",
        string $errorLogPath = "errors/errors.txt",
        string $rulesFile = "rules.json",
        string $outputSchemaFile = "output_schema.json"
    ) {
        $lines = file($credFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->client = new HttpClient([
            'base_uri' => trim($lines[0]),
            'auth' => [trim($lines[1]), trim($lines[2])],
        ]);

        $this->errorLogPath = $errorLogPath;
        $this->rules = json_decode(file_get_contents($rulesFile), true);
        $this->outputSchema = json_decode(file_get_contents($outputSchemaFile), true);
    }

    public function validate(string $imgPath, int $distance, int $elevation, bool $makelogs = true, string $logDir = "logs"): array
    {
        $timestamp = date("y_m_d-H_i_s");
        $outputPath = "$logDir/out_$timestamp.json";

        $inferenceOut = $this->runInference($imgPath);
        if ($makelogs && $inferenceOut !== null) {
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            file_put_contents($outputPath, json_encode($inferenceOut, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $this->accept($inferenceOut ?? [], $distance, $elevation, true);
    }

    private function runInference(string $imagePath): ?array
    {
        try {
            $ruleJson = $this->assessRuleValidity($imagePath);
            if (!$ruleJson['valid_rules']) {
                return $ruleJson;
            }
        } catch (\Throwable $e) {
            file_put_contents($this->errorLogPath, "Rule check error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return null;
        }

        try {
            return array_merge($ruleJson, $this->extractData($imagePath));
        } catch (\Throwable $e) {
            file_put_contents($this->errorLogPath, "Data extraction error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return null;
        }
    }

    private function assessRuleValidity(string $imagePath, string $model = "gemma3:27b"): array
    {
        $image = file_get_contents($imagePath);

        $response = $this->client->post('/chat', [
            'json' => [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $this->rules['rule_definition_prompt']],
                    ['role' => 'assistant', 'content' => $this->rules['assistant_prompt']],
                    ['role' => 'user', 'content' => "Ok. Tady máš obrázek, vyhodnoť validitu obrázku podle pravidel poté odpověz jako JSON.", 'images' => [base64_encode($image)]]
                ],
                'format' => [
                    'type' => 'object',
                    'properties' => [
                        'valid_rules' => ['type' => 'boolean'],
                        'reason_rules' => ['type' => 'string']
                    ],
                    'required' => ['valid_rules']
                ]
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    private function extractData(string $imagePath, string $model = "qwen2.5vl"): array
    {
        $image = file_get_contents($imagePath);

        $response = $this->client->post('/chat', [
            'json' => [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $this->rules['prompt_data'], 'images' => [base64_encode($image)]]
                ],
                'format' => $this->outputSchema
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    private function accept(array $ins, int $dist, int $ele, bool $loosEle = false): array
    {
        try {
            if (!$ins['valid_rules'])
                return [false, 1];
            if (!$this->acceptDistance($ins, $dist))
                return [false, 2];
            if (!$this->acceptElevation($ins, $ele, $loosEle))
                return [false, 3];
            return [true, 0];
        } catch (\Throwable $e) {
            return [false, 4];
        }
    }

    private function acceptDistance(array $ins, int $dist): bool
    {
        return abs($dist - $ins['distance']) < 0.05 * $dist;
    }

    private function acceptElevation(array $ins, int $ele, bool $loosEle): bool
    {
        if ($loosEle && $ins['elevation'] === null)
            return true;
        if ($ele === 0)
            return true;
        return $ele === $ins['elevation'];
    }
}

