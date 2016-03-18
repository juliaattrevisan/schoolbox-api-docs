<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Cloud\Google;

use Alaress\Async\Notification\Cloud\AbstractService;
use Guzzle\Http\Client;
use OutOfBoundsException;
use OverflowException;
use Schoolbox\MobileApp\Registry;


/**
 * Google Cloud Messaging
 *
 * Class GCM
 *
 * @package Alaress\Async\Notification\Cloud\Google
 */
class GCM extends AbstractService
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
        parent::__construct();
        $this->apiKey = $apiKey;
    }


    /**
     * @return string
     */
    public function getProviderName()
    {
        return Registry::PROVIDER_GOOGLE;
    }


    /**
     * @param array  $registrationIds
     * @param int    $id
     * @param string $title
     * @param string $body
     * @return bool
     */
    public function propagate(array $registrationIds, $id, $title, $body)
    {
        $data = [
            'id'      => $id,
            'title'   => $title,
            'body'    => $body,
            'vibrate' => true
        ];

        $apiUri  = self::GCM_API_URI;
        $headers = array(
            "Authorization" => "key={$this->apiKey}",
            "Content-Type"  => "application/json"
        );

        // ensure payload does not exceed 4KB
        if (strlen(json_encode($data)) > self::MAX_PAYLOAD) {
            throw new OverflowException("Maximum payload size is " . self::MAX_PAYLOAD);
        }

        $client   = new Client();

        // break into multiple requests if too many for a single pass
        foreach (array_chunk($registrationIds, self::MAX_TOKENS, false) as $chunk) {
            $payload = json_encode(array(
                'registration_ids' => $chunk,
                'data'             => $data
            ));

            $rq = $client->post($apiUri, $headers, $payload);

            try {
                $rq->send();

            } catch (OutOfBoundsException $e) {
                if ($e->getCode() == 401) {
                    // your API key is bad: don't try to do anything else
                    throw $e;
                }
                $this->getLog()->warning($e->getMessage(), [ $e ]);
            }
            // range exception not caught here
        }

    }

}
