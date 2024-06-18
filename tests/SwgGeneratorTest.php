<?php

declare(strict_types=1);

namespace tests;

use des1roer\swgen\Service\ResponseDto;
use des1roer\swgen\Service\SwgGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @group swg
 * @covers SwgGenerator
 */
final class SwgGeneratorTest extends TestCase
{
    private SwgGenerator $swgGenerator;

    protected function setUp(): void
    {
        $this->swgGenerator = new class extends SwgGenerator {
            /**
             * @var string[]
             */
            public array $texts = [];

            /**
             * @var string[]
             */
            public array $destinations = [];

            protected function saveOutput(string $destination, string $modified): void
            {
                $this->texts[] = $modified;
                $this->destinations[] = $destination;
            }

            public function publicCreateName(string $key): string
            {
                return $this->createName($key);
            }

            public function publicGetArrayMap(array $array): array
            {
                return $this->getArrayMap($array);
            }

            public function publicGetCollection(array $decode, string $key, string $prefix): array
            {
                return $this->getResponseCollection($decode, $key, $prefix);
            }

            public function publicGetClassCollection(array $result, array $input): array
            {
                return $this->getClassCollection($result, $input);
            }
        };
    }

    /**
     * @dataProvider keyProvider
     */
    public function testNameCreation(string $key, string $expected): void
    {
        $result = $this->swgGenerator->publicCreateName($key);
        $this->assertEquals($expected, $result);
    }

    public function keyProvider(): array
    {
        return [
            ['user.first_name', 'User'],
            ['product.name', 'Product'],
            ['order.customer.address.city', 'OrderCustomerAddress'],
        ];
    }

    /**
     * @dataProvider arrayProvider
     */
    public function testGetArrayMap(array $array, array $expected): void
    {
        $result = $this->swgGenerator->publicGetArrayMap($array);
        $this->assertEquals($expected, $result);
    }

    public function arrayProvider(): array
    {
        return [
            [
                ['user' => ['first_name' => 'John', 'last_name' => 'Doe']],
                ['user', 'user.first_name', 'user.last_name'],
            ],
            [
                ['product' => ['name' => 'Laptop', 'price' => 1000]],
                ['product', 'product.name', 'product.price'],
            ],
            [
                ['order' => ['customer' => ['address' => ['city' => 'New York']]]],
                ['order', 'order.customer', 'order.customer.address', 'order.customer.address.city'],
            ],
        ];
    }

    /**
     * @dataProvider collectionProvider
     */
    public function testGetCollection(array $decode, string $key, string $prefix, array $expected): void
    {
        // Act
        $result = $this->swgGenerator->publicGetCollection($decode, $key, $prefix);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function collectionProvider(): array
    {
        return [
            [
                ['value1', 'value2'],
                'testKey',
                'test',
                [
                    ResponseDto::createFromArray('testKey', ['value1', 'value2']),
                ],
            ],
            [
                ['id' => 1, 'name' => 'John'],
                'user',
                'user',
                [
                    ResponseDto::createFromJson('id', 1),
                    ResponseDto::createFromJson('name', 'John'),
                ],
            ],
        ];
    }

    /**
     * @dataProvider classCollectionProvider
     */
    public function testGetClassCollection(array $map, array $data, array $expected): void
    {
        // Act
        $collection = $this->swgGenerator->publicGetClassCollection($map, $data);

        // Assert
        $this->assertEquals($expected, $collection);
    }

    public function classCollectionProvider(): array
    {
        return [
            [
                'map' => ['id', 'nickname', 'type', 'name', 'secondName'],
                'data' => [
                    'id' => 48,
                    'nickname' => 'Tester 1',
                    'type' => 'USER',
                    'name' => 'FirstName 1',
                    'secondName' => 'LastName 1',
                ],
                'expected' => [
                    'base' => [
                        'id' => 48,
                        'nickname' => 'Tester 1',
                        'type' => 'USER',
                        'name' => 'FirstName 1',
                        'secondName' => 'LastName 1',
                    ],
                ],
            ],
            [
                'map' => ['id', 'sub', 'sub.nickname'],
                'data' => [
                    'id' => 48,
                    'sub' => [
                        'nickname' => 'Tester 1',
                    ],
                ],
                'expected' => [
                    'sub' => [
                        'nickname' => 'Tester 1',
                    ],
                    'base' => [
                        'id' => 48,
                        'sub' => 'sub',
                    ],
                ],
            ],
        ];
    }

    public function testGenerateSimple(): void
    {
        $this->swgGenerator->generate(
            '{"id":48,"nickname":"Tester 1","type":"USER","name":"FirstName 1","isUser":true}',
            'User',
            'Get',
            SwgGenerator::TYPE_RESPONSE,
        );

        $listing = <<<'PHP'
<?php

declare(strict_types=1);

namespace Infrastructure\Swagger\User\Response;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     type="object",
 *     required={
 *       "id",
 *       "nickname",
 *       "type",
 *       "name",
 *       "isUser",
 *     }
 * )
 */
final class GetUserApiResponse
{
    /**
     * @var int
     *
     * @OA\Property(
     *     type="integer",
     *     example=48,
     * )
     */
    public $id;

    /**
     * @var string
     *
     * @OA\Property(
     *     type="string",
     *     example="Tester 1",
     * )
     */
    public $nickname;

    /**
     * @var string
     *
     * @OA\Property(
     *     type="string",
     *     example="USER",
     * )
     */
    public $type;

    /**
     * @var string
     *
     * @OA\Property(
     *     type="string",
     *     example="FirstName 1",
     * )
     */
    public $name;

    /**
     * @var bool
     *
     * @OA\Property(
     *     type="bool",
     *     example=false,
     * )
     */
    public $isUser;
}

PHP;

        self::assertCount(1, $this->swgGenerator->texts);
        self::assertEquals($listing, current($this->swgGenerator->texts));
        self::assertCount(1, $this->swgGenerator->destinations);
        self::assertEquals(
            'src/Infrastructure/Swagger/User/Response/GetUserApiResponse.php',
            current($this->swgGenerator->destinations),
        );
    }

    public function testGenerateSubObject(): void
    {
        $this->swgGenerator->generate(
            '{"id":48,"sub":{"nickname":"Tester 1"}}',
            'User',
            'Get',
            SwgGenerator::TYPE_RESPONSE,
        );

        $subListing = <<<'PHP'
<?php

declare(strict_types=1);

namespace Infrastructure\Swagger\User\Response;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     type="object",
 *     required={
 *       "nickname",
 *     }
 * )
 */
final class GetSubApiResponse
{
    /**
     * @var string
     *
     * @OA\Property(
     *     type="string",
     *     example="Tester 1",
     * )
     */
    public $nickname;
}

PHP;

        self::assertCount(2, $this->swgGenerator->texts);
        self::assertEquals($subListing, $this->swgGenerator->texts[0]);
        self::assertCount(2, $this->swgGenerator->destinations);
        self::assertEquals(
            'src/Infrastructure/Swagger/User/Response/GetSubApiResponse.php',
            $this->swgGenerator->destinations[0],
        );

        $mainListing = <<<'PHP'
<?php

declare(strict_types=1);

namespace Infrastructure\Swagger\User\Response;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     type="object",
 *     required={
 *       "id",
 *       "sub",
 *     }
 * )
 */
final class GetUserApiResponse
{
    /**
     * @var int
     *
     * @OA\Property(
     *     type="integer",
     *     example=48,
     * )
     */
    public $id;

    /**
     * @var GetSubApiResponse
     *
     * @OA\Property(
     *     type="object",
     *     ref="#/components/schemas/GetSubApiResponse",
     * )
     */
    public $sub;
}

PHP;

        self::assertEquals($mainListing, $this->swgGenerator->texts[1]);
        self::assertEquals(
            'src/Infrastructure/Swagger/User/Response/GetUserApiResponse.php',
            $this->swgGenerator->destinations[1],
        );
    }

    public function testGenerateBaseList(): void
    {
        $this->swgGenerator->generate(
            '[{"id":48}]',
            'User',
            'Get',
            SwgGenerator::TYPE_RESPONSE,
        );

        $listing = <<<'PHP'
<?php

declare(strict_types=1);

namespace Infrastructure\Swagger\User\Response;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     type="object",
 *     required={
 *       "id",
 *     }
 * )
 */
final class GetUserApiResponse
{
    /**
     * @var int
     *
     * @OA\Property(
     *     type="integer",
     *     example=48,
     * )
     */
    public $id;
}

PHP;

        self::assertCount(1, $this->swgGenerator->texts);
        self::assertEquals($listing, $this->swgGenerator->texts[0]);
        self::assertCount(1, $this->swgGenerator->destinations);
        self::assertEquals(
            'src/Infrastructure/Swagger/User/Response/GetUserApiResponse.php',
            $this->swgGenerator->destinations[0],
        );
    }

    public function testGenerateEqualsValues(): void
    {
        $this->swgGenerator->generate(
            '{"id":48,"text":"text"}',
            'User',
            'Get',
            SwgGenerator::TYPE_RESPONSE,
        );

        $listing = <<<'PHP'
<?php

declare(strict_types=1);

namespace Infrastructure\Swagger\User\Response;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     type="object",
 *     required={
 *       "id",
 *       "text",
 *     }
 * )
 */
final class GetUserApiResponse
{
    /**
     * @var int
     *
     * @OA\Property(
     *     type="integer",
     *     example=48,
     * )
     */
    public $id;

    /**
     * @var string
     *
     * @OA\Property(
     *     type="string",
     *     example="text",
     * )
     */
    public $text;
}

PHP;

        self::assertCount(1, $this->swgGenerator->texts);
        self::assertEquals($listing, $this->swgGenerator->texts[0]);
        self::assertCount(1, $this->swgGenerator->destinations);
        self::assertEquals(
            'src/Infrastructure/Swagger/User/Response/GetUserApiResponse.php',
            $this->swgGenerator->destinations[0],
        );
    }
}
