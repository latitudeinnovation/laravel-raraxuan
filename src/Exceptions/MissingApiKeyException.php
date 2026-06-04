<?php

namespace LatitudeInnovation\Raraxuan\Exceptions;

class MissingApiKeyException extends InvalidConfigurationException
{
    public static function missing(): self
    {
        return new self('Raraxuan API key is not configured. Set RARAXUAN_API_KEY in your .env file.');
    }
}
