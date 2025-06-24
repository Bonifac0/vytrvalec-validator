<?php

namespace bonifac0\validator;

class Validator
{
    public static function validate(int $value): bool
    {
        if ($value > 10)
        {
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }
}
