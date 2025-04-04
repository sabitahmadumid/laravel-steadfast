<?php

namespace SabitAhmad\SteadFast\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \SabitAhmad\SteadFast\SteadFast
 */
class SteadFast extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \SabitAhmad\SteadFast\SteadFast::class;
    }
}
