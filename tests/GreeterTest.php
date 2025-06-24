<?php

use PHPUnit\Framework\TestCase;
use bonifac0\Greeter\Greeter;

class GreeterTest extends TestCase
{
    public function testSayHello()
    {
        $this->assertEquals("Hello, Jan!", Greeter::sayHello("Jan"));
    }
}
