<?php

namespace LatitudeInnovation\Raraxuan\Exceptions;

class InvalidConfigurationException extends RaraxuanException
{
    public static function missingBaseUrl(): self
    {
        return new self('Raraxuan API base URL is not configured.');
    }

    public static function invalidTimeout(): self
    {
        return new self('Raraxuan timeout must be greater than zero.');
    }
}
