<?php

declare(strict_types=1);

namespace tests;

use des1roer\swgen\Service\ResponseDto;
use PHPUnit\Framework\TestCase;

/**
 * @group swg
 * @covers \des1roer\swgen\Service\ResponseDto
 */
class ResponseDtoTest extends TestCase
{
    /**
     * @dataProvider typeProvider
     */
    public function testCreateFromJson(string $key, $value, string $expectedType, string $expectedValue): void
    {
        // Act
        $typeDto = ResponseDto::createFromJson($key, $value);

        // Assert
        $this->assertEquals($key, $typeDto->key);
        $this->assertEquals($expectedType, $typeDto->type);
        $this->assertEquals($expectedValue, $typeDto->phpDoc);
    }

    public function typeProvider(): array
    {
        return [
            ['testKey', 'testValue', 'string', 'example="testValue",'],
            ['intKey', 123, 'int', 'example=123,'],
            ['intKey', 0.1, 'float', 'example="0.1",'],
            ['boolKey', true, 'bool', 'example=false,'],
            [
                'mixedKey',
                ['value1', 'value2'],
                'string[]',
                'type="array",' . PHP_EOL . '     *     @OA\Items(type="string"),',
            ],
        ];
    }

    /**
     * @dataProvider definitionProvider
     */
    public function testCreateDefinition(
        string $column,
        string $name,
        string $expectedType,
        string $expectedValue
    ): void {
        // Act
        $typeDto = ResponseDto::createDefinition($column, $name);

        // Assert
        $this->assertEquals($expectedType, $typeDto->type);
        $this->assertEquals($column, $typeDto->key);
        $this->assertEquals($expectedValue, $typeDto->phpDoc);
    }

    public function definitionProvider(): array
    {
        return [
            ['testColumn', 'Test', 'TestApiResponse', 'ref="#/components/schemas/TestApiResponse",'],
            ['id', 'User', 'UserApiResponse', 'ref="#/components/schemas/UserApiResponse",'],
            ['name', 'Product', 'ProductApiResponse', 'ref="#/components/schemas/ProductApiResponse",'],
        ];
    }

    /**
     * @dataProvider arrayDefinitionProvider
     */
    public function testCreateFromArray(string $key, array $decode, string $expectedType, string $expectedValue): void
    {
        // Act
        $typeDto = ResponseDto::createFromArray($key, $decode);

        // Assert
        $this->assertEquals($key, $typeDto->key);
        $this->assertEquals($expectedType, $typeDto->type);
        $this->assertEquals($expectedValue, $typeDto->phpDoc);
    }

    public function arrayDefinitionProvider(): array
    {
        return [
            'listString' => [
                'testKey',
                ['value1', 'value2'],
                'string[]',
                'type="array",' . PHP_EOL . '     *     @OA\Items(type="string"),',
            ],
            'listInt' => [
                'id',
                [1, 2, 3],
                'int[]',
                'type="array",' . PHP_EOL . '     *     @OA\Items(type="integer"),',
            ],
            'associativeString' => [
                'data',
                [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
                'string[]',
                'type="array",' . PHP_EOL . '     *     @OA\Items(type="string"),',
            ],
        ];
    }
}
