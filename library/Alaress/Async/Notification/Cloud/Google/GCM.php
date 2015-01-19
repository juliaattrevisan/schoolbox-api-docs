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
use Guzzle\Http\Client;
use OutOfBoundsException;
use OverflowException;
use RangeException;


/**
 * Google Cloud Messaging
 *
 * Class GCM
 *
 * @package Alaress\Async\Notification\Cloud\Google
 */
class GCM implements ServiceInterface
{

    const GCM_API_URI = 'https://android.googleapis.com/gcm/send';
    const MAX_PAYLOAD = 4096;
    const MAX_TOKENS  = 1000;

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
     * @param array $registrationIds
     * @param array $data
     * @throws OverflowException     if the intended payload would be too large
     * @throws OutOfBoundsException  if the GCM API key is invalid
     * @throws RangeException        if GCM needs us to back off
     * @return void
     */
    public function send(array $registrationIds, array $data)
    {
        $apiUri  = self::GCM_API_URI;
        $headers = array(
            "Authorization: key=$this->apiKey",
            'Content-Type: application/json'
        );

        // ensure payload does not exceed 4KB
        if (strlen(json_encode($data)) > self::MAX_PAYLOAD) {
            throw new OverflowException("Maximum payload size is " . self::MAX_PAYLOAD);
        }

        $client   = new Client();
//        $response = new Response();

        // break into multiple requests if too many for a single pass
        foreach (array_chunk($registrationIds, self::MAX_TOKENS, false) as $chunk) {
            $payload = json_encode(array(
                'registration_ids' => $chunk,
                'data'             => $data
            ));

            $rq = $client->post($apiUri, $headers, $payload);

            try {
//                $response->append($registrationIds, $rq->send());
                $rq->send();

            } catch (OutOfBoundsException $e) {
                if ($e->getCode() == 401) {
                    // your API key is bad: don't try to do anything else
                    throw $e;
                }
                // TODO log $e as a non-fatal error
                echo $e;
            }
            // range exception not caught here
        }

//        return $response;
    }

} 