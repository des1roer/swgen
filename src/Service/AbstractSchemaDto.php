<?php

namespace des1roer\swgen\Service;

use  des1roer\swgen\Utils\Arr;

abstract class AbstractSchemaDto
{
    public string $key = '';
    public string $type = '';
    public string $swgType = '';
    public string $phpDoc = '';

    final private function __construct()
    {
    }

    /**
     * @param mixed $value
     */
    public static function createFromJson(string $key, $value): self
    {
        if (is_array($value)) {
            return self::createFromArray($key, $value);
        }

        $typeDto = new static();
        $typeDto->type = self::checkType($value);
        $typeDto->swgType = self::checkSwgType($typeDto->type);
        $typeDto->key = $key;

        $typeDto->phpDoc = sprintf(
            'example=%s,',
            self::formatValue($typeDto, $value),
        );

        return $typeDto;
    }

    /**
     * @param mixed $value
     */
    private static function formatValue(self $dto, $value): string
    {
        if ($dto->type === 'bool') {
            return 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return sprintf('"%s"', $value);
    }

    /**
     * @param array<mixed> $decode
     */
    public static function createFromArray(string $key, array $decode): self
    {
        $typeDto = new static();
        $key = Arr::last(explode('.', $key));

        $current = current($decode);
        $type = 'string';
        if (is_string($current)) {
            $typeDto->type = 'string[]';
        } elseif (is_int($current)) {
            $typeDto->type = 'int[]';
            $type = 'integer';
        } else {
            $typeDto->type = 'array<mixed>';
        }

        $typeDto->key = $key;
        $typeDto->phpDoc = 'type="array",' . PHP_EOL . sprintf('     *     @OA\Items(type="%s"),', $type);

        return $typeDto;
    }

    public static function createDefinition(string $column, string $name): self
    {
        $typeDto = new static();
        $typeDto->type = $name . static::getPostfix();
        $typeDto->swgType = 'type="object",';
        $typeDto->key = $column;
        $typeDto->phpDoc = sprintf('ref="#/components/schemas/%s%s",', $name, static::getPostfix());

        return $typeDto;
    }

    /**
     * @param mixed $value
     */
    private static function checkType($value): string
    {
        if (is_int($value)) {
            return 'int';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_bool($value)) {
            return 'bool';
        }

        return 'string';
    }

    private static function checkSwgType(string $value): string
    {
        if ($value === 'int') {
            return 'type="integer",';
        }

        if ($value === 'bool') {
            return 'type="bool",';
        }

        return 'type="string",';
    }

    public function toString(): string
    {
        $str = '

    /**
     * @var %s
     *
     * @OA\Property(
     *     %s
     *     %s
     * )
     */
    public $%s;';

        return sprintf($str, $this->type, $this->swgType, $this->phpDoc, $this->key);
    }

    abstract protected static function getPostfix(): string;
}
