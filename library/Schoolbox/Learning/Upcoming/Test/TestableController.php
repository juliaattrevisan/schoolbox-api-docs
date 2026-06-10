<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Upcoming\Test;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Schoolbox\Learning\Upcoming\Controller;

/**
 * Test double that captures Upcoming controller render data.
 */
class TestableController extends Controller
{
    private array $renderedData = [];

    /**
     * @return array<string, mixed>
     */
    public function getRenderedData(): array
    {
        return $this->renderedData;
    }

    /**
     * Captures template data that would normally be rendered into an unthemed response.
     */
    protected function responseFromTemplateUnthemed(string $template, array $data): ResponseInterface
    {
        $this->renderedData = $data;

        return new Response();
    }

    /**
     * Returns a JSON response for controller paths that bypass template rendering.
     */
    protected function responseAsJson(mixed $data, bool $buffer = false, ?string $filename = null): ResponseInterface
    {
        return new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR));
    }
}
