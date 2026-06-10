<?php

namespace LatitudeInnovation\Raraxuan\Exceptions;

class InvalidConfigurationException extends RaraxuanException
{
    public static function missingBaseUrl(): self
    {
        return new self('Raraxuan API base URL is not configured.');
    }

    public static function missingProcessPath(): self
    {
        return new self('Raraxuan API prompt process path is not configured.');
    }

    public static function missingPingPath(): self
    {
        return new self('Raraxuan API ping path is not configured.');
    }

    public static function invalidTimeout(): self
    {
        return new self('Raraxuan timeout must be greater than zero.');
    }
}
