<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use RuntimeException;

/**
 * Class Api
 */
class Api implements IApi
{

    /**
     * The path to where the kamar xml was cached
     *
     * Kamar pushes a document to us. We cache it locally and read from it
     * just as if we had reached out and acquired it ourselves. That file
     * is cached in $path
     *
     * @var string
     */
    private $path;


    /**
     * Constructor
     *
     * @param string $path
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }


    /**
     * Find out if the xml file exists on the server
     * If it doesn't exist we can't do much with it
     */
    public function isXmlFileExtant(): bool
    {
        return file_exists($this->path) && is_readable($this->path);
    }


    /**
     * Retrieves a XML document representing the result from the API call.
     *
     * @return string|null
     * @throws RuntimeException if the retrieval of the XML doc failed
     */
    public function retrieveDocument(): ?string
    {
        if ($this->isXmlFileExtant()) {
            return file_get_contents($this->path);
        }

        throw new RuntimeException("cannot open or read {$this->path}");
    }


    /**
     * The file currently being consumed by this IApi instance
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
