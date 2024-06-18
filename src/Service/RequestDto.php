<?php

namespace des1roer\swgen\Service;

/**
 * @method static self createFromJson(string $key, mixed $value)
 * @method static self createFromArray(string $key, array $decode)
 * @method static self createDefinition(string $column, string $name)
 */
class RequestDto extends AbstractSchemaDto
{
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
    private $%s;';

        return sprintf($str, $this->type, $this->swgType, $this->phpDoc, $this->key);
    }

    protected static function getPostfix(): string
    {
        return 'ApiRequest';
    }
}
