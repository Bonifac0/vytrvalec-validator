<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use bonifac0\VytrvalecValidator\Validator;

class ValidatorTest extends TestCase {
    public function testValidateReturnsTrueWhenValueIsGreaterThan10() {
        $this->assertTrue(Validator::validate(15));
    }

    public function testValidateReturnsFalseWhenValueIs10OrLess() {
        $this->assertFalse(Validator::validate(10));
        $this->assertFalse(Validator::validate(5));
    }
}