<?php

namespace Arg\Laravel\Enums;

use ReflectionClass;

abstract class ArgBaseEnum
{

    public static function getAll(): array
    {
        return (new ReflectionClass(new static()))->getConstants();
    }
    public static function getAllReverted(): array
    {
        $all = (new static())::getAll();
        return array_flip($all);
    }
    public static function getValues(): array
    {
        return array_values((new static())::getAll());
    }

    public static function toColumnComment(): string
    {
        return implode(', ', (new static())::getAll());
    }
}
