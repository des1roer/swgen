<?php

namespace des1roer\swgen\Service;

/**
 * @method static self createFromJson(string $key, mixed $value)
 * @method static self createFromArray(string $key, array $decode)
 * @method static self createDefinition(string $column, string $name)
 *
 * @see \tests\ResponseDtoTest
 */
class ResponseDto extends AbstractSchemaDto
{
    protected static function getPostfix(): string
    {
        return 'ApiResponse';
    }
}
