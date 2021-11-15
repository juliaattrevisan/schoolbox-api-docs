<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

use Alaress\Codec\ICodec;
use Laminas\Uri\UriInterface;

/**
 * Codec for generating identifiers in URI format.
 *
 * Encodes an assessment ID into a URI which represents the assessment, and vice
 * versa.
 *
 * (Note that the URI may not actually be a valid URL.)
 */
abstract class UriBasedCodec implements ICodec
{
    /**
     * Standard directory separator for paths.
     */
    private const SEPARATOR = '/';

    /**
     * Base URI, used for encoding the raw value into a URI.
     */
    private UriInterface $baseUri;

    /**
     * Constructor
     *
     * @param UriInterface $baseUri
     */
    public function __construct(UriInterface $baseUri)
    {
        $this->baseUri = $baseUri;
    }

    /**
     * Encode the raw value into a URI string.
     *
     * @param mixed $rawValue
     * @return string
     */
    public function encode($rawValue)
    {
        return (clone $this->baseUri)
            ->setPath($this->retrieveBasePath() . self::SEPARATOR . $rawValue)
            ->setQuery(null)
            ->setFragment(null)
            ->toString();
    }

    /**
     * Decodes the URI string into a raw value.
     *
     * @param string $encodedValue
     * @return mixed|null
     */
    public function decode($encodedValue)
    {
        $path = parse_url($encodedValue, PHP_URL_PATH);
        if (!is_string($path)) {
            return null;
        }

        // Remove $this->retrieveBasePath() from the start of the path
        $expectedBasePath       = $this->retrieveBasePath();
        $expectedBasePathLength = strlen($expectedBasePath);
        $actualBasePath         = substr($path, 0, $expectedBasePathLength);
        return $expectedBasePath === $actualBasePath
            ? substr($path, $expectedBasePathLength + 1)
            : null;
    }

    /**
     * Retrieve the base path to use in this codec.
     *
     * @return string
     */
    abstract protected function retrieveBasePath(): string;
}
