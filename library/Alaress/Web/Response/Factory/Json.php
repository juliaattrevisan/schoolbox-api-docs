<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Response\Factory;

use Alaress\Web\Response\IFactory;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A factory for a JSON response.
 */
class Json implements IFactory
{
    /**
     * Constructor
     *
     * @param ResponseFactoryInterface $responseFactory A response factory
     * @param array|object             $data            Data to encode in JSON
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private array|object $data,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function build(): ResponseInterface
    {
        return $this->responseFactory->createResponse()
            ->withHeader('content-type', 'application/json')
            ->withBody(Utils::streamFor(json_encode($this->data)));
    }
}
