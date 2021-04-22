<?php

namespace Titus\Beatle;

class Greetr
{
    public static function greet(String $sName)
    {
        return 'Hi ' . $sName . '! How are you doing today?';
    }
}