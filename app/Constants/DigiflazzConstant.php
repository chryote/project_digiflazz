<?php

namespace App\Constants;

class DigiflazzConstant
{
    /**
     * Get configured Digiflazz merchant username from environment.
     */
    public static function username(): ?string
    {
        return config('digiflazz.username');
    }

    /**
     * Get configured Digiflazz development / API key from environment.
     */
    public static function developmentKey(): ?string
    {
        return config('digiflazz.development_key');
    }

    /**
     * Alias for developmentKey().
     */
    public static function apiKey(): ?string
    {
        return self::developmentKey();
    }
}
