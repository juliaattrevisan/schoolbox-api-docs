<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Importer;


/**
 * Class Batchxml
 *
 * ISAMS Batch File processor. Reads from an api endpoint, eats the xml there,
 * then caches a xpath-traversable which you can repeatedly call without having
 * to incur the cost of redownloading the xml.
 *
 * The cached xpath-traversable lives for runtime duration, it doesn't write it
 * to disk or anything like that.
 *
 * @package Schoolbox\External\UPS
 */
class Batchxml
{

    /**
     * @var Api
     */
    private $api;


    /**
     * Cache the xml nodes that we downloaded so we don't end up re-fetching it
     * if we traverse the xml more than once
     *
     * @var \SimpleXMLElement
     */
    private $nodes;


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
     * @return \SimpleXMLElement[]
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    public function fetchXml(string $path): array
    {
//        $this->printXml();

        if (!$this->nodes) {
            $this->nodes = new \SimpleXMLElement($this->api->retrieveDocument());
        }

        return $this->nodes->xpath($path);
    }


    /**
     * Use this to print a vaguely-formatted version to a file for manual inspection
     *
     * e.g. php profileImporter.php > test.xml
     */
    private function printXml(): void
    {
        print_r(str_replace(['<', '/>'], ["\n<", "/>\n"],$this->api->retrieveDocument()));
        die();
    }
}
