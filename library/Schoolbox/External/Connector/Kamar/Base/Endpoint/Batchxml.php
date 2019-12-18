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
 * Class Xml
 */
class Batchxml
{

    /**
     * @var IApi
     */
    private $api;


    /**
     * Cache the xml nodes that we downloaded so we don't end up re-fetching it
     * if we traverse the xml more than once
     *
     * @var SimpleXMLElement
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
     * @return SimpleXMLElement[]
     * @throws RuntimeException
     */
    public function fetchXml(string $path): array
    {
        /* If you need to see the raw xml data from your chosen endpoint
         * you can drop a printxml() here, which does some minor formatting to
         * make it vaguely bearable to read
         *
         * $this->printXml();
         */
        if (!$this->nodes) {
            $xml = $this->api->retrieveDocument();

            if (strpos($xml, '<') !== 0) {
                throw new RuntimeException($xml);
            }

            try {
                $this->nodes = new SimpleXMLElement($xml);
            } catch (\Exception $e) {

                /* Kamar returned some sort of error message, not xml
                 */
                throw new RuntimeException("{$e->getMessage()}\n\n{$xml}");
            }
        }

        return $this->nodes->xpath($path);
    }

    /**
     * Use this to print a vaguely-formatted version to a file for manual inspection
     *
     * e.g. php sisImporter.php > test.xml
     */
    private function printXml(): void
    {
        print_r(str_replace(['<', '/>'], ["\n<", "/>\n"], $this->api->retrieveDocument()));
        die();
    }
}
