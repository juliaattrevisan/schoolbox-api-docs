<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Cloud\Google;

use Alaress\Async\Notification\Cloud\ServiceInterface;
use OverflowException;


/**
 * Google Cloud Messaging
 *
 * Class GCM
 *
 * @package Alaress\Async\Notification\Cloud\Google
 */
class GCM implements ServiceInterface
{

    /** @var string */
    protected $apiKey;


    /**
     * @param string $apiKey
     */
    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }


    /**
     * @return string
     */
    public function getProviderName()
    {
        return 'gcm';
    }


    /**
     * @param array $tokens
     * @param array $data
     * @throws OverflowException
     * @return bool
     */
    public function send(array $tokens, array $data)
    {
        // ensure payload does not exceed 4KB
        if (strlen(json_encode($data)) > 4096) {
            throw new OverflowException("Maximum data size is 4KB.");
        }

        // break into multiple requests if too many for a single pass
        foreach (array_chunk($tokens, 1000, false) as $chunk) {

            $response = $this->cUrl(array(
                'registration_ids' => $chunk,
                'data'             => $data
            ));

            switch ($response['code'][0]) {
                case '2': // success

                    $data = json_decode($response['body'], true);

                    if ($data['failure'] + $data['canonical_ids'] == 0) {
                        // nothing more need be don here. onto the next request
                        continue;
                    }

                    foreach ($data['results'] as $i => $detail) {
                        if (isset($detail['message_id'])) {
                            if (isset($detail['registration_id'])) {
                                // you need to update your tokens
                                $tokens[$i] = $detail['registration_id'];
                                // TODO get this into the database
                            }
                        }
                    }

                case '4': // our fault
                case '5': // el goog's fault
            }

        }
    }


    /**
     * @return resource
     */
    protected function cUrl($data)
    {
        $apiUri  = 'https://android.googleapis.com/gcm/send';
        $headers = array(
            "Authorization: key=$this->apiKey",
            'Content-Type: application/json'
        );

        $ch = curl_init($apiUri); // TODO proxify this

        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
        ));

        $response = curl_exec($ch);

        curl_close($ch);

        list($headers, $body) = explode("\n\n", $response, 2);

        $output = array(
            'status' => '',
            'headers' => array(),
            'body' => $body
        );

        foreach (explode("\n", $headers) as $line) {
            if (strpos($line, 'HTTP/1.') === 0) {
                $output['status'] = substr($line, 9, 3);
                continue;
            }

            $header = explode(": ", $line);
            $output['headers'][$header[0]] = $header[1];
        }

        return $output;
    }

} 