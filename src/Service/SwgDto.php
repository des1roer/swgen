<?php

declare(strict_types=1);

namespace des1roer\swgen\Service;

class SwgDto
{
    private const TEMPLATE = '
     * @OA\%s(
     *          path="%s",
     *          summary="CHANGE_ME",
     *          tags={"CHANGE_ME"},
%s
%s
%s
%s
     * )
     ';

    private string $path;
    private string $entity;
    private string $prefix;

    /**
     * @var int[]
     */
    private array $codes;

    /**
     * @param int[] $codes
     */
    public function __construct(
        string $path,
        string $entity,
        string $prefix,
        array $codes
    ) {
        $this->path = $path;
        $this->entity = $entity;
        $this->prefix = ucwords(strtolower($prefix));
        $this->codes = $codes;
    }

    public function toString(): string
    {
        return sprintf(
            self::TEMPLATE,
            $this->prefix,
            $this->path,
            $this->parameters(),
            $this->request(),
            $this->successResponse(),
            $this->badRequests(),
        );
    }

    private function parameters(): string
    {
        preg_match_all('/\{([^}]+)\}/', $this->path, $matches);

        $res = '';

        $str = '
     *          @OA\Parameter(
     *              name="%s",
     *              in="path",
     *              @OA\Schema(type="string"),
     *              required=true,
     *          ),';
        foreach ($matches[1] as $parameter) {
            $res .= sprintf($str, $parameter);
        }

        return ltrim($res, PHP_EOL);
    }

    private function request(): string
    {
        $str = '
     *          @OA\RequestBody(
     *              required=true,
     *              @OA\JsonContent(ref=%sApiRequest::class),
     *          ),';

        return ltrim(sprintf($str, $this->prefix . $this->entity), PHP_EOL);
    }

    private function successResponse(): string
    {
        $str = '
     *          @OA\Response(
     *              response=%s,
     *              description="Успешный ответ",
     *              @OA\JsonContent(ref=%sApiResponse::class),
     *          ),';

        if (current($this->codes) < 300) {
            return trim(sprintf($str, current($this->codes), $this->prefix . $this->entity), PHP_EOL);
        }

        return '';
    }

    private function badRequests(): string
    {
        if (current($this->codes) < 300) {
            array_shift($this->codes);
        }

        $str = '
     *          @OA\Response(response=%s, description="%s"),';
        $res = '';
        foreach ($this->codes as $code) {
            $res .= sprintf($str, $code, HttpCodeEnum::$statusTexts[$code] ?? 'Bad Request');
        }

        return ltrim($res, PHP_EOL);
    }
}
