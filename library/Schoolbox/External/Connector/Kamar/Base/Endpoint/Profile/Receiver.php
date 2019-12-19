<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile;

use http\Exception\RuntimeException;
use SimpleXMLElement;

/**
 * Class Receiver
 *
 * Receive an xml payload and save it locally
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile
 */
class Receiver
{

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
        $this->suffix = date('Ymd').'_'.date('His') . '.xml';
    }


    /**
     * @param string $data
     * @throws RuntimeException
     */
    public function receive(string $data): void
    {
        /* file_get_contents returns mixed data string|bool
         * Interpret that and respond accordingly
         */
        if (false === $data) {
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
     * @param string $data
     * @throws RuntimeException
     */
    private function process(string $data): void
    {
        $nodes = new SimpleXMLElement($data);

        /* Figure out the prefix for this payload's filename
         */
        $prefix = (string)$nodes->xpath('/SMSDirectoryData/@sync')[0];
        $file   = "{$this->dir}/{$prefix}_$this->suffix";

        /* Write xml file
         */
        $file = @fopen($file, 'w');

        if (!$file) {
            throw new RuntimeException("Unable to open file {$file} for local caching.");
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
</SMSDirectoryData>
XML;

        echo (new SimpleXMLElement($string))->asXML();
    }
}
