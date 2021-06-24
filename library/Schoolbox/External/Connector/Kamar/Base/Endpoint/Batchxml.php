<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use RuntimeException;
use SimpleXMLElement;

/**
 * Class Batchxml
 *
 * Kamar Batch File processor. Reads from a local filesystem endpoint, eats the xml there,
 * then caches a xpath-traversable which you can repeatedly call without having
 * to incur the cost of re-consuming the xml.
 *
 * The cached xpath-traversable lives for runtime duration, it doesn't write it
 * to disk or anything like that.
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint
 */
class Batchxml
{

    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * Cache the xml nodes that we downloaded so we don't end up re-fetching it
     * if we traverse the xml more than once
     *
     * @var SimpleXMLElement|null
     */
    private ?SimpleXMLElement $nodes = null;


    /**
     * Kamar returned some sort of error message, not xml
     * Display the first 200 chars of the broken data
     */
    private const DEBUG_CHARS = 200;


    /**
     * Batchxml constructor.
     *
     * @param IApi $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;
    }


    /**
     * Fetch xml contents
     *
     * If it was already downloaded once before, don't download it again
     *
     * @param string $path
     * @return SimpleXMLElement[]
     * @throws RuntimeException
     */
    public function fetchXml(string $path): array
    {
        if (!$this->nodes) {
            $xml = $this->api->retrieveDocument();

            if (strpos($xml, '<') !== 0) {
                throw new RuntimeException($xml);
            }

            try {
                $this->nodes = new SimpleXMLElement($xml);
            } catch (\Exception $e) {
                /* Kamar returned some sort of error message, not xml
                 * Display the first 200 chars of the broken data
                 */
                throw new RuntimeException("{$e->getMessage()}, {$path}\n\n" . substr($xml, 0, self::DEBUG_CHARS));
            }
        }

        return $this->nodes->xpath($path);
    }
}
