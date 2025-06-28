<?php

use PHPUnit\Framework\TestCase;

#require_once __DIR__ . '/../src/Validator.php';
use bonifac0\VytrvalecValidator\Validator;


class ValidatorTest extends TestCase
{
    private Validator $validator;
    private string $imagePath;

    protected function setUp(): void
    {
        $this->validator = new Validator(
            __DIR__ . '/../credentials.txt',
            __DIR__ . '/../errors/errors_test.txt',
            __DIR__ . '/../rules.json',
            __DIR__ . '/../output_schema.json'
        );

        $this->imagePath = __DIR__ . '/../testdata/test_image.jpg';
        $this->assertFileExists($this->imagePath, "Test image file missing.");
    }

    public function testRunInferenceReturnsValidStructure()
    {
        try {
            $data = $this->invokePrivateMethod($this->validator, 'runInference', [$this->imagePath]);
            $this->assertIsArray($data);
            $this->assertArrayHasKey('valid_rules', $data);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->markTestSkipped("API returned 401 Unauthorized – check your credentials.txt");
        }
    }


    public function testFullValidationReturnsExpectedFormat()
    {
        [$isValid, $errorCode] = $this->validator->validate(
            $this->imagePath,
            distance: 7900,
            elevation: 110,
            makelogs: false
        );

        $this->assertIsBool($isValid);
        $this->assertIsInt($errorCode);
    }


    public function testExtractDataReturnsFields()
    {
        $data = $this->invokePrivateMethod($this->validator, 'extractData', [$this->imagePath]);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('distance', $data);
        $this->assertArrayHasKey('type_of_excercise', $data);
    }

    public function testAssessRuleValidityIncludesFlag()
    {
        $data = $this->invokePrivateMethod($this->validator, 'assessRuleValidity', [$this->imagePath]);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('valid_rules', $data);
    }

    private function invokePrivateMethod(object $object, string $methodName, array $args = []): mixed
    {
        $refClass = new ReflectionClass($object);
        $method = $refClass->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}
