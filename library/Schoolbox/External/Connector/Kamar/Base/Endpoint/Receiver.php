<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use \RuntimeException;
use SimpleXMLElement;

/**
 * Class Receiver
 *
 * Receive an xml payload and save it locally
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint
 */
class Receiver
{

    /**
     * The prefixes we can expect to receive
     * These are the SMSDirectoryData node's @sync attribute
     */
    public const XML_FULL = 'full';
    public const XML_STUDENTTIMETABLES = 'studenttimetables';


    /**
     * @var string
     */
    private $dir;


    /**
     * @var string
     */
    private $suffix;


    /**
     * Receiver constructor.
     *
     * @param $dir
     */
    public function __construct($dir)
    {
        $this->dir = $dir;
        $this->suffix = uniqid('_', true) . '.xml';
    }


    /**
     * Kamar sends their payload data to this endpoint
     *
     * @param string $data
     * @throws RuntimeException
     */
    public function receive(string $data): void
    {
        if ('' === $data) {
            $string = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SMSDirectoryData>
        <error>401</error>
        <result>No Data</result>
        <service>Example</service>
        <version>1.0</version>
        <status>Ready</status>
</SMSDirectoryData>
XML;

            echo (new SimpleXMLElement($string))->asXML();
        } else {
            $this->process($data);
        }
    }


    /**
     * Process the payload data that kamar sent to us, caching it to a local file
     * named after an xml attribute's value
     *
     * @param string $data
     * @throws RuntimeException
     */
    private function process(string $data): void
    {
        $nodes = new SimpleXMLElement($data);

        /* Figure out the prefix for this payload's filename
         *
         * Kamar sends profile data and timetable data separately. They're not in the same file.
         * The payloads arrive one after the other, but not in any prescribed order so the timetable
         * data could come before profile data, or vice versa, at any given moment.
         *
         * We need a way to conclusively determine which payload was sent to us. We could inspect nodes
         * or we can just use a uniquely identifying attribute in the root node which must always be
         * supplied. The @sync attribute seems to be identifying payloads and for our purposes will be
         * either 'full' or 'studenttimetables' if the docs are to be believed. The value of @sync is
         * stored in $prefix.
         *
         * For now, while we're testing,
         *   Profile data will end up in /mnt/data/schoolbox/cache/stream/full_YYYMMDD_HHIISS.xml
         *   Timetable data will end up in /mnt/data/schoolbox/cache/stream/studenttimetables_YYYMMDD_HHIISS.xml
         *
         * I'll probably have to remove the datetime later (reasons will become clear) but we need to record
         * each push attempt that the school makes while we're figuring out this integration.
         */
        $prefix = (string)$nodes->xpath('/SMSDirectoryData/@sync')[0];
        $path = "{$this->dir}/{$prefix}{$this->suffix}";

        /* Write xml file
         */
        $file = @fopen($path, 'w');

        if (!$file) {
            throw new RuntimeException("Unable to open file {$path} for local caching.");
        }

        fwrite($file, $data);
        fclose($file);

        /* Generate content-received response
         */
        $string = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SMSDirectoryData>
        <error>0</error>
        <result>OK</result>
        <service>Example</service>
        <version>1.0</version>
        <status>Ready</status>
        <options>
            <ics>false</ics>
            <students>
                <details>true</details>
                <timetables>true</timetables>
                <learningsupport>true</learningsupport>
            </students>
            <staff>
                <details>true</details>
                <photos>true</photos>
                <timetables>true</timetables>
            </staff>
        </options>
</SMSDirectoryData>
XML;

        echo (new SimpleXMLElement($string))->asXML();
    }
}
