<?php

namespace bonifac0\validatornamespace;

class Validatorclass
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
