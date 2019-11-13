<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Notification\Cloud\Google;

use Alaress\Async\Notification\Cloud\AbstractService;
use GuzzleHttp\Client;
use Monolog\Logger;
use OverflowException;
use RuntimeException;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;


/**
 * Google Cloud Messaging
 *
 * Class GCM
 *
 * @package Alaress\Async\Notification\Cloud\Google
 */
class GCM extends AbstractService
{

    const GCM_API_URI = 'https://fcm.googleapis.com/fcm/send';
    const MAX_PAYLOAD = 4096;
    const MAX_TOKENS  = 1000;

    /** @var string */
    protected $apiKey;


    /**
     * @param RegistryMapper $registryMapper
     * @param string         $appIdentifier
     * @param string         $apiKey
     */
    public function __construct(RegistryMapper $registryMapper, $appIdentifier, $apiKey)
    {
        parent::__construct($registryMapper, $appIdentifier);
        $this->apiKey = $apiKey;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
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
     * @param int    $unreadCount
     * @throws RuntimeException if your API key is bad
     * @throws OverflowException if the max payload size (4KB) is exceeded
     * @return bool
     */
    public function propagate(array $registrationIds, $id, $title, $unreadCount = 0)
    {
        $data = [
            'id'           => $id,
            'title'        => ConfigLoader::getInstance()->get('title'),
            'body'         => $title,
            'vibrate'      => true,
            'click_action' => 'MainActivity',
            'image'        => 'www/img/hi-res-icon.png'
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

            try {
                $rs = $client->post(
                    $apiUri, [
                        'headers' => $headers,
                        'body'    => $payload,
                    ]
                );

                $statusCode = $rs->getStatusCode();
                if ($statusCode < 200 || $statusCode >= 300) {
                    throw new RuntimeException("Non-200 response from GCM: {$statusCode}");
                }

                $body = json_decode($rs->getBody()->getContents(), true);

                foreach ($body['results'] as $i => $result) {
                    $regId = $registrationIds[$i];

                    if (isset($result['error'])) {
                        $this->report("$regId", $result['error'], Logger::INFO, '?');

                        if ($result['error'] == 'NotRegistered') {
                            // we need to delete this token, it will only break things
                            $this->deleteToken($regId);
                        }

                    } else {
                        $this->report("$regId", "All ok!", Logger::DEBUG);
                    }
                }

            } catch (RuntimeException $e) {
                if ($e->getCode() == 401) {
                    // your API key is bad: don't try to do anything else
                    throw $e;
                }
                $this->getLog()->warning($e->getMessage(), [ 'exception'=>$e ]);
            }
            // range exception not caught here
        }

    }

}
