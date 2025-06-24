<?php
namespace bonifac0\VytrvalecValidator;

class Validator {
    public static function validate(int $value): bool {
        return $value > 10;
    }
}