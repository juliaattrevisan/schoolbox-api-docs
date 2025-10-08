<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Api\Batch;

use RuntimeException;
use SimpleXMLElement;

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
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class Batchxml
{
    /**
     * A surety for maximum length of error messages.
     */
    private const MAX_ERROR_MESSAGE_LENGTH = 255;


    /**
     * Cache the xml nodes that we downloaded so we don't end up re-fetching it
     * if we traverse the xml more than once
     *
     * @var SimpleXMLElement|null
     */
    private ?SimpleXMLElement $nodes = null;


    /**
     * Batchxml constructor.
     *
     * @param IApi $api
     */
    public function __construct(private IApi $api)
    {
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
         * $this->printXml(true);
         */
        if (!$this->nodes) {
            $xml = trim($this->api->retrieveDocument());

            /* Test if $xml is actually xml, or some other random jank
             *
             * Specifically catch root level exception since SimpleXmlElement throws
             * them. It also circumstantially captures all other Exception subclasses
             */
            try {
                /* If this succeeds, $xml is the actual isams batch xml contents
                 * and we're good to go
                 */
                $this->nodes = @(new SimpleXMLElement($xml));

                /* Note about Suppression
                 *
                 * I *know* it looks weird having a suppression on a constructor,
                 * but $xml from isams can be malformed as per the following commentary
                 * about Severe Warning. The suppression prevents warnings being shown
                 * on the Specification screen when you misconfigure the api key
                 */
            } catch (\Exception $e) {
                /* iSAMS can throw you either a plain-jane text string:
                 * "iSAMS blocked the request as it came from an unknown source."
                 *
                 * ...or this beauty:
                 *
                    <?xml version="1.0" encoding="utf-16"?>
                    <Message>
                      <MessageId>MissingParameter</MessageId>
                      <MessageName>MissingParameter</MessageName>
                      <Title>Missing Field: apiKey</Title>
                      <Description>A required field "apiKey" was not supplied or was in an incorrect format.</Description>
                    </Message>

                 * Which means we have to try to load *that* as xml next.
                 *
                 * Severe Warning:
                 * SimpleXml will tell you "String could not be parsed as XML" because of this:
                 *
                 *     Entity: line 1: parser error : Document labelled UTF-16 but has UTF-8 content
                 *
                 * iSAMS is telling us the wrong encoding. Forcibly rewrite utf-16 into utf-8 which
                 * is what it really is, when an error message occurs (e.g. invalid api key)
                 */
                try {
                    $errorXml = str_replace('utf-16', 'utf-8', $xml);

                    /* Shank out the Description
                     */
                    $errorNodes = @(new SimpleXMLElement($errorXml));

                    $errorMessage = (string)$errorNodes->xpath('/Message')[0]->Description;
                } catch (\Exception $e) {
                    /* Some non-xml returned from isams: use it as-is
                     */
                    $errorMessage = $e->getMessage();
                }

                /* If it's longer than 255, truncate it for the log
                 */
                $errorMessage = mb_substr(trim($errorMessage), 0, self::MAX_ERROR_MESSAGE_LENGTH, 'utf-8');

                /* Announce whatever error message we might have found
                 */
                throw new RuntimeException($errorMessage);
            }
        }

        return $this->nodes->xpath($path);
    }


    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->api->getOptions();
    }


    /**
     * Use this to print a vaguely-formatted version to a file for manual inspection
     *
     * e.g. php sisImporter.php > test.xml
     *
     * Optionally provide an arg to determine whether you want pseudo-formatted output
     * or the raw single-line xml string
     *
     * @param bool $formatted
     */
//    private function printXml($formatted = false): void
//    {
//        if ($formatted) {
//            print_r(str_replace('><', ">\n<", $this->api->retrieveDocument()));
//        } else {
//            print_r($this->api->retrieveDocument());
//        }
//
//        die();
//    }
}
