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
     * Check request string, contained in the @sync attribute of SMSDirectoryData
     */
    private const XML_CHECK = 'check';


    /**
     * @var string
     */
    private $dir;


    /**
     * @var string
     */
    private $suffix;


    /**
     * Basic authorization string
     * base64encoded username/password combination
     *
     * @var string
     */
    private $expectedAuthorization;


    /**
     * Receiver constructor.
     *
     * @param string $dir
     * @param string $apiKey
     */
    public function __construct(string $dir, string $apiKey)
    {
        $this->dir = $dir;
        $this->suffix = '_' . date('YmdHis') . uniqid('_', true) . '.xml';

        /* Authorization header pieces
         * These should be sent in the headers of every message Kamar sends to us
         * Passwords need to be at least 6 chars long sigh
         *
         * This is stored in the SUDS School Data Endpoint Key field as a colon-separated tuple:
         * e.g. "schoolbox:schoolboxkamar"
         */
        $this->expectedAuthorization = 'Basic ' . base64_encode($apiKey);
    }


    /**
     * Kamar sends their payload data to this endpoint
     *
     * @param string $data
     * @throws RuntimeException
     */
    public function receive(string $data): void
    {

        /* Check Authorization in the payload's headers
         */
        $this->checkAuthorization();

        /* $data is what the kamar instance sent us
         */
        if ('' === $data) {
            /* if $data is empty, send a No Data response payload
             */
            $string = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SMSDirectoryData>
    <error>401</error>
    <result>No Data</result>
    <service>Schoolbox</service>
    <version>1.0</version>
    <status>Ready</status>
</SMSDirectoryData>
XML;

            echo (new SimpleXMLElement($string))->asXML();
        } else {
            $xml = new SimpleXMLElement($data);

            /* The value of @sync attribute in SMSDirectoryData
             */
            $syncVal = (string)$xml->xpath('/SMSDirectoryData/@sync')[0];

            if (self::XML_CHECK === $syncVal) {
                /* If $data contains the sync:check attribute in the SMSDirectoryData node
                 * then it's a check request for when a Kamar instance is enabled (or re-enabled)
                 * We need to treat it specially
                 *
                 * Note: the docs are inconsistent, some places say the check request happens
                 * upon configuration, other places suggest it happens on every payload request;
                 * not sure which is right, if any
                 */
                $this->processCheck();
            } else {
                /* Otherwise presume it's one of the normal data payloads and save it
                 * to a local xml for later processing
                 */
                $this->process($data);
            }
        }
    }


    /**
     * Check that the kamar payload contained the required authentication parts
     * If it doesn't, send out an error xml
     */
    private function checkAuthorization(): void
    {

        /* Authorization header might not exist
         */
        $authorizationString = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        /* If the authorization string doesn't match our expected value,
         * then the request is invalid; reject it and end
         */
        if ($authorizationString !== $this->expectedAuthorization) {
            $string = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SMSDirectoryData>
    <error>403</error>
    <result>Authentication Failed</result>
    <service>Schoolbox</service>
    <version>1.0</version>
</SMSDirectoryData>
XML;

            http_response_code(403);
            echo (new SimpleXMLElement($string))->asXML();
            exit();
        }
    }


    /**
     * Kamar was enabled for the first time,
     * or re-enabled after being deactivated
     *
     * We need to re-confirm that we're who we say we are
     */
    private function processCheck(): void
    {
        $string = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SMSDirectoryData>
    <error>0</error>
    <result>OK</result>
    <service>Schoolbox</service>
    <version>1.0</version>
    <status>Ready</status>
    <infourl>https://www.schoolbox.com.au</infourl>
    <privacystatement>For more information about Schoolbox privacy policy please refer to https://schoolbox.com.au/legal</privacystatement>
    <options>
        <students>
            <details>true</details>
            <passwords>false</passwords>
            <photos>false</photos>
            <groups>true</groups>
            <awards>false</awards>
            <timetables>true</timetables>
            <attendance>false</attendance>
            <assessments>false</assessments>
            <pastoral>false</pastoral>
            <learningsupport>true</learningsupport>
            <fields>
                <required>nsn;firstname;lastname;username;siblinglink</required>
                <optional>uniqueid;emergency;emergency1;emergency2;emergency.name;emergency.relationship;emergency.mobile;genderpreferred;boarder;datebirth;yearlevel;house;startingdate;leavingdate;mobile;tutor;esol;flags;flag.conditions;flag.specialneeds;flag.notes;caregivers;caregivers1;caregivers2;caregivers3;caregivers4;caregiver.name;caregiver.relationship;caregiver.mobile;caregiver.email</optional>
            </fields>
        </students>
        <staff>
            <details>true</details>
            <passwords>false</passwords>
            <photos>true</photos>
            <timetables>true</timetables>
            <fields>
                <required>firstname;lastname;username</required>
                <optional>classification;email;extension;position;title;groups;groups.departments</optional>
            </fields>
        </staff>
        <common>
            <subjects>true</subjects>
            <notices>false</notices>
            <calendar>false</calendar>
            <bookings>false</bookings>
        </common>
    </options>
</SMSDirectoryData>
XML;

        echo (new SimpleXMLElement($string))->asXML();
        exit();
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
         *   Profile data will end up in /mnt/data/schoolbox/cache/stream/full_YYYMMDDHHIISS_xxxxx.xml
         *   Timetable data will end up in /mnt/data/schoolbox/cache/stream/studenttimetables_YYYMMDDHHIISS_xxxxx.xml
         *   Partial Timetable data will end up in /mnt/data/schoolbox/cache/stream/part_studenttimetables_YYYMMDDHHIISS_xxxxx.xml
         *
         * I'll probably have to remove the datetime later (reasons will become clear) but we need to record
         * each push attempt that the school makes while we're figuring out this integration.
         */
        $prefix = (string)$nodes->xpath('/SMSDirectoryData/@sync')[0];

        /* Inspect a studenttimetables file to find out how many nodes it has
         * If it's just one node, it's actually a part_studenttimetables file and we want to disregard it
         */
        if ((self::XML_STUDENTTIMETABLES === $prefix) && 1 === (int)$nodes->xpath('/SMSDirectoryData/timetables/@count')[0]) {
            $prefix = 'part_studenttimetables_';
        }

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
</SMSDirectoryData>
XML;

        echo (new SimpleXMLElement($string))->asXML();
        exit();
    }
}
