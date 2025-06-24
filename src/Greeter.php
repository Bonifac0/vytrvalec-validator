<?php

namespace bonifac0\Greeter;

class Greeter
{
    public static function sayHello(string $name): string
    {
        return "Hello, $name!";
    }
}
