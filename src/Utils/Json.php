<?php

declare(strict_types=1);

namespace des1roer\swgen\Utils;

class Json
{
    /**
     * @throws \JsonException
     */
    public static function decode(string $json): array
    {
        return \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }

    private function __construct()
    {
    }
}