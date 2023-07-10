<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Response\Factory;

use Alaress\Web\Response\IFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A factory for a redirect response.
 */
class Redirect implements IFactory
{
    /**
     * Constructor
     *
     * @param ResponseFactoryInterface $responseFactory A response factory
     * @param string                   $location        Where to redirect to?
     * @param int                      $statusCode      The status code to redirect with
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private string $location,
        private int $statusCode,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function build(): ResponseInterface
    {
        return $this->responseFactory->createResponse($this->statusCode)
            ->withHeader('location', $this->location);
    }
}
