<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Cloud\Digistorm;

use Alaress\Async\Notification\Cloud\AbstractService;
use Alaress\Async\Notification\Notification;
use Alaress\Async\Notification\Cloud\RateLimitException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;
use SensitiveParameter;

/**
 * Digistorm notification service
 */
class Service extends AbstractService
{
    /**
     * The URL to which notifications are pushed
     */
    private const BASE_URL = 'https://api.notifications.digistorm.io/v1/push';

    /**
     * Base values to pass in the payload to requests to the Digistorm
     * notification API.
     */
    private const BASE_PAYLOAD = [
        'id_type'  => 'username',
        'platform' => 'schoolbox',
        'module'   => 'messages',
    ];

    /**
     * API key for the service
     */
    private string $key;

    /**
     * The app's title: this title may be used as the title of notifications.
     */
    private string $appTitle;

    /**
     * Constructor
     *
     * @param RegistryMapper $registryMapper
     * @param string         $appIdentifier
     * @param string         $key
     * @param string         $appTitle
     */
    public function __construct(
        RegistryMapper $registryMapper,
        $appIdentifier,
        #[SensitiveParameter] string $key,
        string $appTitle
    ) {
        parent::__construct($registryMapper, $appIdentifier);
        $this->key      = $key;
        $this->appTitle = $appTitle;
    }

    /**
     * @inheritdoc
     */
    public function getProviderName(): string
    {
        return Registry::PROVIDER_DIGISTORM;
    }

    /**
     * @inheritdoc
     */
    protected function propagate(array $tokens, $id, $title, $unreadCount = 0, ?Notification $notification = null)
    {
        $headers = [
            'X-Auth-Token' => $this->key,
            'Content-Type' => 'application/json',
        ];
        $payload            = self::BASE_PAYLOAD;
        $payload['title']   = $this->appTitle;
        $payload['message'] = $title;
        $payload['ids']     = $tokens;

        // need a HTTP client (should this be injected?)
        $client = new Client();
        try {
            $response = $client->post(
                self::BASE_URL,
                [
                    RequestOptions::BODY    => json_encode($payload),
                    RequestOptions::HEADERS => $headers,
                ]
            );

            // Check the response: maybe not all IDs exist?
            // That might be an issue worth checking?
            $responseContent = json_decode($response->getBody()->getContents(), true);
            $errors          = $responseContent['errors'] ?? [];
            if (is_iterable($errors) && count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->getLog()->notice(
                        sprintf(
                            'Possible error response: %s: %s',
                            $error['description'] ?? '',
                            $error['id'] ?? '',
                        )
                    );
                }
            } else {
                $this->getLog()->info('Sent successfully');
            }
            return true;
        } catch (GuzzleException $e) {
            $responseCode = $e->getCode();
            if ($responseCode === 401) {
                // your API key is bad: don't try to do anything else
                $this->getLog()->error('Received response code 401: this means the API key is incorrect');
                throw new \OutOfBoundsException($e->getMessage(), $e->getCode(), $e);
            }

            // Some error other than rate-limit? raise an error
            if ($responseCode !== 429) {
                $this->getLog()->warning($e->getMessage(), ['exception' => $e]);
                return false;
            }

            // Throw a rate limit exception for later handling
            throw new RateLimitException('Digistorm service is rate-limited', 0, $e);
        }
    }
}
