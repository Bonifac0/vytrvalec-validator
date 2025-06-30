<?php
namespace bonifac0\VytrvalecValidator;

use bonifac0\OllamaClient\OllamaClient;
use PhpParser\Builder\Method;

class Validator
{
    private OllamaClient $client;
    private string $errorLogPath;
    private string $outLogPath;
    private array $prompts;
    private array $outputSchema;

    public function __construct(
        string $credFile = __DIR__ . '/../resources/credentials.txt',
        string $errorLogFile = __DIR__ . '/../errors/errors.txt',
        string $outLogFile = __DIR__ . '/../logs/validationLogs.json',
        string $promptsFile = __DIR__ . '/../resources/payload_prompts.json',
        string $outputSchemaFile = __DIR__ . '/../resources/data_output_format.json'
    ) {
        $lines = file($credFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->client = new OllamaClient(trim($lines[0]), [trim($lines[1]), trim($lines[2])]);

        $this->errorLogPath = $errorLogFile;
        $this->outLogPath = $outLogFile;
        $this->prompts = json_decode(file_get_contents($promptsFile), true);
        $this->outputSchema = json_decode(file_get_contents($outputSchemaFile), true);

        if (!is_dir(dirname($errorLogFile))) {
            mkdir(dirname($errorLogFile), 0777, true);
        }
        if (!is_dir(dirname($outLogFile))) {
            mkdir(dirname($outLogFile), 0777, true);
        }
    }

    public function validate(string $imgPath, int $distance, int $elevation, bool $makelogs = true): array
    {
        $inferenceOut = $this->runInference($imgPath);

        if ($makelogs && $inferenceOut !== null) {
            $this->makeLog($inferenceOut, $imgPath);
        }

        return $this->accept($inferenceOut, $distance, $elevation, true);
    }
    private function makeLog(array $inference, string $imgPath = ""): void
    {
        $logs = [];

        if (file_exists($this->outLogPath)) {
            $existing = file_get_contents($this->outLogPath);
            $decoded = json_decode($existing, true);
            if (is_array($decoded)) {
                $logs = $decoded;
            }
        }

        $logEntry = [
            "time" => date("y_m_d-H_i_s"),
            "content" => $inference
        ];
        if ($imgPath !== "") {
            $logEntry["image"] = $imgPath;
        }

        $logs[] = [$logEntry];

        file_put_contents(
            $this->outLogPath,
            json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function runInference(string $imagePath): array
    {
        try {
            $ruleJson = $this->assessRuleValidity($imagePath);
            if (!$ruleJson['valid_rules']) {
                return $ruleJson;
            }
        } catch (\Throwable $e) {
            file_put_contents($this->errorLogPath, "Rule check error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return ['valid_rules' => false,];
        }

        try {
            return array_merge($ruleJson, $this->extractData($imagePath));
        } catch (\Throwable $e) {
            file_put_contents($this->errorLogPath, "Data extraction error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return ['valid_rules' => false,];
        }
    }

    private function assessRuleValidity(string $imagePath, string $model = "gemma3:27b"): array
    {
        $image = file_get_contents($imagePath);
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $this->prompts['rule_definition']],
                ['role' => 'assistant', 'content' => $this->prompts['understand']],
                [
                    'role' => 'user',
                    'content' => "Ok. Tady máš obrázek, vyhodnoť validitu obrázku podle pravidel poté odpověz jako JSON.",
                    'images' => [base64_encode($image)]
                ]
            ],
            'format' => [
                'type' => 'object',
                'properties' => [
                    'valid_rules' => ['type' => 'boolean'],
                    'reason_rules' => ['type' => 'string']
                ],
                'required' => ['valid_rules']
            ]
        ];
        return $this->client->chat($payload);
    }

    private function extractData(string $imagePath, string $model = "qwen2.5vl"): array
    {
        $image = file_get_contents($imagePath);
        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $this->prompts['data_extract'],
                    'images' => [base64_encode($image)]
                ]
            ],
            'format' => $this->outputSchema
        ];

        return $this->client->chat($payload);
    }

    private function accept(array $ins, int $dist, int $ele, bool $loosEle = false): array
    {
        try {
            if (!$ins['valid_rules']) {
                return [false, 1];
            }
            if (!$this->acceptDistance($ins, $dist)) {
                return [false, 2];
            }
            if (!$this->acceptElevation($ins, $ele, $loosEle)) {
                return [false, 3];
            }

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

