<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Notification\Cloud\Google;

use Alaress\Async\Notification\Cloud\AbstractService;
use Alaress\Async\Notification\Notification;
use GuzzleHttp\Client;
use Monolog\Logger;
use OverflowException;
use RuntimeException;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\User;
use SensitiveParameter;

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
     * The app's title: this title will be used as the title of notifications.
     */
    private string $appTitle;

    /**
     * Constructor
     *
     * @param RegistryMapper $registryMapper
     * @param string         $appIdentifier
     * @param string         $apiKey
     * @param string         $appTitle
     */
    public function __construct(
        RegistryMapper $registryMapper,
        $appIdentifier,
        #[SensitiveParameter] $apiKey,
        string $appTitle
    ) {
        parent::__construct($registryMapper, $appIdentifier);
        $this->apiKey   = $apiKey;
        $this->appTitle = $appTitle;
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
     * @inheritdoc
     *@throws OverflowException if the max payload size (4KB) is exceeded
     * @throws RuntimeException if your API key is bad
     */
    public function propagate(array $tokens, $id, $title, $unreadCount = 0, ?Notification $notification = null, ?User $recipient = null)
    {
        $data = [
            'id'           => $id,
            'title'        => $this->appTitle,
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
        foreach (array_chunk($tokens, self::MAX_TOKENS, false) as $chunk) {
            $payload = json_encode(array(
                'registration_ids' => $chunk,
                'data'             => $data
            ));

            try {
                $rs = $client->post(
                    $apiUri,
                    [
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
                    $regId = $tokens[$i];

                    if (isset($result['error'])) {
                        $this->report("$regId", $result['error'], Logger::NOTICE, '?');

                        if ($result['error'] == 'NotRegistered') {
                            // we need to delete this token, it will only break things
                            $this->deleteToken($regId);
                        }
                    } else {
                        $this->report("$regId", "All ok!", Logger::INFO);
                    }
                }
            } catch (RuntimeException $e) {
                if ($e->getCode() == 401) {
                    // your API key is bad: don't try to do anything else
                    throw new \OutOfBoundsException($e->getMessage(), $e->getCode(), $e);
                }
                $this->getLog()->warning($e->getMessage(), [ 'exception' => $e ]);
            }
            // range exception not caught here
        }
    }
}
