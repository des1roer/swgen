<?php

declare(strict_types=1);

namespace des1roer\swgen\Service;

use Exception;
use des1roer\swgen\Utils\Arr;
use des1roer\swgen\Utils\Json;
use RuntimeException;

/**
 * @see \tests\SwgGeneratorTest
 */
class SwgGenerator
{
    public const TYPE_RESPONSE = 'response';
    public const TYPE_REQUEST = 'request';

    /**
     * @var array<mixed>
     */
    private array $classCollection = [];

    /**
     * @throws \JsonException
     */
    public function generate(
        string $json,
        string $entity,
        string $prefix,
        string $type
    ): string {
        if (!in_array($type, [self::TYPE_RESPONSE, self::TYPE_REQUEST], true)) {
            throw new RuntimeException('Invalid type');
        }

        $input = Json::decode($json);
        $prefix = ucwords(strtolower($prefix));

        if ($type === self::TYPE_RESPONSE) {
            return $this->generateResponse(
                $input,
                $entity,
                $prefix,
            );
        }

        return $this->generateRequest(
            $input,
            $entity,
            $prefix,
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    protected function generateResponse(
        array $input,
        string $entity,
        string $prefix
    ): string {
        if (Arr::isList($input)) {
            $input = current($input);
        }

        $result = $this->getArrayMap($input);
        $classCollection = $this->getClassCollection($result, $input);

        foreach ($classCollection as $key => $item) {
            $this->processResponse($key, $item, $entity, $prefix);
        }

        return "src/Infrastructure/Swagger/{$entity}/Response";
    }

    /**
     * @param array<string, mixed> $input
     */
    protected function generateRequest(
        array $input,
        string $entity,
        string $prefix
    ): string {
        if (Arr::isList($input)) {
            $input = current($input);
        }

        $result = $this->getArrayMap($input);
        $classCollection = $this->getClassCollection($result, $input);

        foreach ($classCollection as $key => $item) {
            $this->processRequest($key, $item, $entity, $prefix);
        }

        return "src/Infrastructure/Swagger/{$entity}/Request";
    }

    /**
     * @param array<string, mixed> $array = [
     *     'user' => ['first_name' => 'John', 'last_name' => 'Doe']
     * ]
     * @return string[] = [
     *     'user', 'user.first_name', 'user.last_name'
     * ]
     * @see \tests\SwgGeneratorTest::testGetArrayMap
     */
    protected function getArrayMap(array $array): array
    {
        $paths = [];

        foreach ($array as $key => $value) {
            $currentPath = (string) $key;
            $paths[] = $currentPath;

            if (is_array($value)) {
                $nestedPaths = $this->getArrayMap($value);
                foreach ($nestedPaths as $nestedPath) {
                    $paths[] = $currentPath . '.' . $nestedPath;
                }
            }
        }

        return $paths;
    }

    /**
     * @param array<string, mixed> $decode
     */
    protected function processRequest(
        string $key,
        array $decode,
        string $entity,
        string $prefix
    ): void {
        $baseEntity = $entity;
        $dirPath = "src/Infrastructure/Swagger/{$baseEntity}/Request";
        if (!is_dir($dirPath)) {
            self::createDirectory($dirPath);
        }

        if ('base' !== $key) {
            $entity = ucwords($key) . $this->createName($key);
        }

        $entityWithPrefix = $prefix . $entity;
        $destination = $dirPath . '/' . $entityWithPrefix . 'ApiRequest.php';
        if (file_exists($destination)) {
            return;
        }

        $collection = $this->getRequestCollection($decode, $key, $prefix);

        $properties = $list = '';
        foreach ($collection as $item) {
            $properties .= $item->toString();
            $list .= " *       \"$item->key\"," . PHP_EOL;
        }

        $source = dirname(__DIR__) . '/Template/ApiRequest.tpl';
        $modified = $this->createDefinition($source, $properties, $entityWithPrefix, $list, $baseEntity);


        $this->saveOutput($destination, $modified);
    }

    /**
     * @param array<string, mixed> $decode
     */
    protected function processResponse(
        string $key,
        array $decode,
        string $entity,
        string $prefix
    ): void {
        $baseEntity = $entity;
        $dirPath = "src/Infrastructure/Swagger/{$baseEntity}/Response";
        if (!is_dir($dirPath)) {
            self::createDirectory($dirPath);
        }

        if ('base' !== $key) {
            $entity = ucwords($key) . $this->createName($key);
        }

        $entityWithPrefix = $prefix . $entity;
        $destination = $dirPath . '/' . $entityWithPrefix . 'ApiResponse.php';
        if (file_exists($destination)) {
            return;
        }

        $collection = $this->getResponseCollection($decode, $key, $prefix);

        $properties = $list = $parameters = $constructor = '';
        foreach ($collection as $item) {
            $properties .= $item->toString();
            $list .= " *       \"$item->key\"," . PHP_EOL;
            $parameters .= "        $$item->key," . PHP_EOL;
            $constructor .= '        $this->' . $item->key . " = $$item->key;" . PHP_EOL;
        }

        $source = dirname(__DIR__) . '/Template/ApiResponse.tpl';
        $modified = $this->createDefinition($source, $properties, $entityWithPrefix, $list, $baseEntity);
        $modified = preg_replace('/{{ARGUMENTS}}/', rtrim($parameters, ',' . PHP_EOL), $modified);
        $modified = preg_replace('/{{CONSTRUCTOR}}/', rtrim($constructor, PHP_EOL), $modified);

        $this->saveOutput($destination, $modified);
    }

    /**
     * @see \tests\SwgGeneratorTest::testNameCreation
     */
    protected function createName(string $key): string
    {
        $explode = explode('.', $key);
        unset($explode[count($explode) - 1]);
        $result = [];
        foreach ($explode as $value) {
            $result[] = ucwords($value);
        }

        return implode('', $result);
    }

    protected static function createDirectory(string $path, int $mode = 0775, bool $recursive = true): bool
    {
        if (is_dir($path)) {
            return true;
        }
        $parentDir = dirname($path);
        // recurse if parent dir does not exist and we are not at the root of the file system.
        if ($recursive && ($parentDir !== $path) && !is_dir($parentDir)) {
            self::createDirectory($parentDir, $mode);
        }
        try {
            if (!mkdir($path, $mode) && !is_dir($path)) {
                return false;
            }
        } catch (Exception $e) {
            if (!is_dir($path)) {
                throw new Exception("Failed to create directory \"$path\": " . $e->getMessage(), $e->getCode(), $e);
            }
        }
        try {
            return chmod($path, $mode);
        } catch (Exception $e) {
            throw new Exception(
                "Failed to change permissions for directory \"$path\": " . $e->getMessage(),
                $e->getCode(),
                $e,
            );
        }
    }

    protected function saveOutput(
        string $destination,
        string $modified
    ): void {
        file_put_contents(
            $destination,
            $modified,
        );
    }

    /**
     * @param array<string, mixed> $decode
     * @return ResponseDto[]
     * @see \tests\SwgGeneratorTest::testGetCollection
     */
    protected function getResponseCollection(array $decode, string $key, string $prefix): array
    {
        $collection = [];
        if (Arr::isList($decode)) {
            $collection[] = ResponseDto::createFromArray($key, $decode);
        } else {
            foreach ($decode as $column => $value) {
                if ($column === $value && isset($this->classCollection[$value])) {
                    $collection[] = ResponseDto::createDefinition(
                        $column,
                        $prefix . ucwords($column) . $this->createName($value),
                    );
                } else {
                    $collection[] = ResponseDto::createFromJson($column, $value);
                }
            }
        }

        return $collection;
    }

    /**
     * @param array<string, mixed> $decode
     * @return RequestDto[]
     */
    protected function getRequestCollection(array $decode, string $key, string $prefix): array
    {
        $collection = [];
        if (Arr::isList($decode)) {
            $collection[] = RequestDto::createFromArray($key, $decode);
        } else {
            foreach ($decode as $column => $value) {
                if ($column === $value && isset($this->classCollection[$value])) {
                    $collection[] = RequestDto::createDefinition(
                        $column,
                        $prefix . ucwords($column) . $this->createName($value),
                    );
                } else {
                    $collection[] = RequestDto::createFromJson($column, $value);
                }
            }
        }

        return $collection;
    }

    /**
     * @param string[] $map = [
     *     'id', 'nickname',
     * ]
     * @param array<string, mixed> $data = [
     *     'id' => 48, 'nickname' => 'Tester 1',
     * ]
     * @return array<mixed> = [
     *   'base' => [
     *       'id' => 48,
     *       'nickname' => 'Tester 1',
     *   ]
     * ]
     * @see \tests\SwgGeneratorTest::testGetClassCollection
     */
    protected function getClassCollection(array $map, array $data): array
    {
        $classCollection = [];
        $list = [];
        foreach ($map as $key) {
            $level = substr_count($key, '.');
            $list[$level] = $level;
        }

        for ($i = count($list) - 1; $i >= 0; $i--) {
            foreach ($map as $key) {
                if (0 === substr_count($key, '.')) {
                    $val = Arr::get($data, $key);
                    if (is_array($val)) {
                        $classCollection['base'][$key] = $key;
                    } else {
                        $classCollection['base'][$key] = $val;
                    }
                } elseif ($i === substr_count($key, '.')) {
                    $path = explode('.', $key);
                    unset($path[count($path) - 1]);
                    $path = implode('.', $path);

                    if (Arr::has($data, $key)) {
                        $value = Arr::get($data, $path);

                        if (is_array($value)) {
                            $last = Arr::last(explode('.', $path));
                            $classCollection[$last] = $value;
                            Arr::set($data, $path, $last);
                        }
                    }
                }
            }
        }
        unset($classCollection[0]);
        asort($classCollection);

        $this->classCollection = $classCollection;

        return $classCollection;
    }

    protected function createDefinition(
        string $source,
        string $properties,
        string $entityWithPrefix,
        string $list,
        string $baseEntity
    ): string {
        $source = file_get_contents($source);

        if (false === $source) {
            throw new RuntimeException('Could not read source file');
        }

        $modified = preg_replace('/{{PROPERTIES}}/', ltrim($properties, PHP_EOL), $source);

        $definition = '
/**
 * @OA\Schema(
 *     type="object",
 *     required={
%s
 *     }
 * )
 */';
        $definition = sprintf($definition, rtrim($list, PHP_EOL));
        $modified = preg_replace('/{{BASE_DEFINITION}}/', $definition, $modified);
        $modified = preg_replace('/{{ENTITY}}/', $baseEntity, $modified);
        $modified = preg_replace('/{{NAME}}/', $entityWithPrefix, $modified);

        return $modified;
    }
}
