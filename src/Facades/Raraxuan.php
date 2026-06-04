<?php

namespace LatitudeInnovation\Raraxuan\Facades;

use Illuminate\Support\Facades\Facade;

class Raraxuan extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'raraxuan';
    }
}
