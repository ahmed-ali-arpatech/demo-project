<?php

namespace Titus\Beatle;

class Greeter
{
    public static function greet(String $sName)
    {
        return 'Hi ' . $sName . '! How are you doing today?';
    }

    public static function greet2(String $sName)
    {
        return 'Hello ' . $sName . '! How have you been Mr. '.$sName.' ?';
    }
}