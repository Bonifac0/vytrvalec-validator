<?php
namespace bonifac0\VytrvalecValidator;

use bonifac0\OllamaClient\OllamaClient;

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

    /**
     * Validates whether the activity data extracted from an image matches the declared distance and elevation.
     *
     * This is the main entry point to the library. It accepts an image (from a local file or URL),
     * extracts structured activity data using AI inference, optionally logs the result, and verifies it
     * against the user-provided distance and elevation.
     *
     * Error codes:
     *  - 0 = Valid
     *  - 1 = Image violates base activity rules
     *  - 2 = Declared distance doesn't match extracted data
     *  - 3 = Declared elevation doesn't match extracted data
     *  - 4 = Inference or validation failed internally
     *
     * @param string $imgPath   Path or URL to the image containing activity data (e.g. a screenshot).
     * @param int    $distance  Distance claimed by the user in meters.
     * @param int    $elevation Elevation gain claimed by the user in meters.
     * @param bool   $makelogs  If true, the inference output will be logged.
     *
     * @return array{
     *     0: bool,   // true if valid, false otherwise
     *     1: int     // error code (see above)
     * }
     */
    public function validate(string $imgPath, int $distance, int $elevation, bool $makelogs = true): array
    {
        $image = file_get_contents($imgPath);
        $inferenceOut = $this->runInference($image);

        if ($makelogs && $inferenceOut !== null) {
            $this->makeLog($inferenceOut, $imgPath);
        }

        return $this->accept($inferenceOut, $distance, $elevation, true);
    }

    /**
     * Appends an inference result to the JSON log file.
     *
     * @param array  $inference The result from the inference step.
     * @param string $imgPath   Optional path or URL to the image used in validation.
     */
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

    /**
     * Runs rule validity check and data extraction on the given image.
     *
     * @param string $image Binary contents of the image.
     * @return array Combined result from rule validation and data extraction.
     */
    private function runInference(string $image): array
    {
        try {
            $ruleJson = $this->assessRuleValidity($image);
            if (!$ruleJson['valid_rules']) {
                return $ruleJson;
            }
        } catch (\Throwable $e) {
            file_put_contents($this->errorLogPath, "Rule check error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return ['valid_rules' => false,];
        }

        try {
            return array_merge($ruleJson, $this->extractData($image));
        } catch (\Throwable $e) {
            file_put_contents($this->errorLogPath, "Data extraction error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return ['valid_rules' => false,];
        }
    }

    /**
     * Checks whether the image satisfies the Vytrvalec rules.
     *
     * @param string $image Binary image data.
     * @param string $model The model name used for rule checking (default: gemma3:27b).
     * @return array Result containing at least the 'valid_rules' key.
     */
    private function assessRuleValidity(string $image, string $model = "gemma3:27b"): array
    {
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

    /**
     * Extracts structured activity data (e.g., distance, elevation) from the image.
     *
     * @param string $image Binary image data.
     * @param string $model The model name used for data extraction.
     * @return array Extracted data fields as defined in the output schema.
     */
    private function extractData(string $image, string $model = "qwen2.5vl"): array
    {
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

    /**
     * Verifies if extracted data matches declared distance and elevation.
     *
     * @param array $ins     The inference result with extracted data.
     * @param int   $dist    Declared distance by the user.
     * @param int   $ele     Declared elevation by the user.
     * @param bool  $loosEle Whether to allow missing elevation data.
     * @return array Result: [bool $isValid, int $errorCode]
     */
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

    /**
     * Checks whether the extracted distance is within 5% of the declared distance.
     *
     * @param array $ins  Inference result with extracted distance.
     * @param int   $dist Declared distance.
     * @return bool True if within acceptable range, false otherwise.
     */
    private function acceptDistance(array $ins, int $dist): bool
    {
        return abs($dist - $ins['distance']) < 0.05 * $dist;
    }

    /**
     * Checks whether the extracted elevation matches the declared elevation.
     *
     * If elevation is zero or missing (and $loosEle is true), validation passes.
     *
     * @param array $ins      Inference result with extracted elevation.
     * @param int   $ele      Declared elevation.
     * @param bool  $loosEle  Allow null elevation if true.
     * @return bool True if elevation is acceptable, false otherwise.
     */
    private function acceptElevation(array $ins, int $ele, bool $loosEle): bool
    {
        if ($loosEle && $ins['elevation'] === null)
            return true;
        if ($ele === 0)
            return true;
        return $ele === $ins['elevation'];
    }
}