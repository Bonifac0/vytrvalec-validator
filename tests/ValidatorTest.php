<?php

use PHPUnit\Framework\TestCase;
use bonifac0\VytrvalecValidator\Validator;

class ValidatorTest extends TestCase
{
    private Validator $validator;
    private string $imagePathGood;
    private string $imagePathFish;
    private string $imgFile;


    protected function setUp(): void
    {
        $this->validator = new Validator();

        $this->imagePathGood = __DIR__ . '/../testdata/test_image_good.jpg';
        $this->assertFileExists($this->imagePathGood, "Test image file missing.");

        $this->imagePathFish = __DIR__ . '/../testdata/fish.jpg';
        $this->assertFileExists($this->imagePathFish, "Fish image file missing.");

        $this->imgFile = file_get_contents($this->imagePathGood);

    }

    public function testFullValidationAccept()
    {
        [$isValid, $errorCode] = $this->validator->validate(
            $this->imagePathGood,
            distance: 7900,
            elevation: 110,
            makelogs: false
        );

        $this->assertIsBool($isValid);
        $this->assertIsInt($errorCode);
        $this->assertTrue($isValid);
        $this->assertEquals(0, $errorCode);
    }
    public function testFullValidationFromURL()
    {
        [$isValid, $errorCode] = $this->validator->validate(
            "https://vytrvalec.uts.zcu.cz/uploads/6626743e121af5.10397810.jpg",
            distance: 15820,
            elevation: 128,
            makelogs: false
        );

        $this->assertEquals(0, $errorCode);
    }

    public function testValidationRejectCodes()
    {
        [$isValid, $errorCode] = $this->validator->validate(
            $this->imagePathFish,
            distance: 7900,
            elevation: 110,
            makelogs: false
        );
        $this->assertFalse($isValid);
        $this->assertEquals(1, $errorCode);

        $inference = $this->invokePrivateMethod($this->validator, 'runInference', [$this->imgFile]);

        [$isValid, $errorCode] = $this->invokePrivateMethod($this->validator, 'accept', [$inference, 4200, 110]);
        $this->assertFalse($isValid);
        $this->assertEquals(2, $errorCode);

        [$isValid, $errorCode] = $this->invokePrivateMethod($this->validator, 'accept', [$inference, 7900, 42]);
        $this->assertFalse($isValid);
        $this->assertEquals(3, $errorCode);
    }

    public function testRunInferenceReturnsValidStructure()
    {
        $data = $this->invokePrivateMethod($this->validator, 'runInference', [$this->imgFile]);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('valid_rules', $data);

        $datafish = $this->invokePrivateMethod($this->validator, 'runInference', [$this->imagePathFish]);

        $this->assertIsArray($datafish);
        $this->assertArrayHasKey('valid_rules', $datafish);
    }


    public function testExtractDataReturnsFields()
    {
        $data = $this->invokePrivateMethod($this->validator, 'extractData', [$this->imgFile]);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('distance', $data);
        $this->assertArrayHasKey('elevation', $data);
    }

    public function testRejectFraud()
    {
        #TODO add fraud filter and testing
        $this->assertTrue(true);
    }


    private function invokePrivateMethod(object $object, string $methodName, array $args = []): mixed
    {
        $refClass = new ReflectionClass($object);
        $method = $refClass->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}
