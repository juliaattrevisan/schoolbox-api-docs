<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Cloud\Digistorm;

use Alaress\Async\Notification\Cloud\AbstractService;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Cloud\RateLimitException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\User;
use SensitiveParameter;

/**
 * Digistorm notification service.
 *
 * Implements the Digistorm notifications API as documented on
 * https://notifications.digistorm.io/docs/v1/Getting_Started.html.
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
    protected function propagate(array $tokens, $id, $title, $unreadCount = 0, ?Instance $instance = null, ?User $recipient = null): bool
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
            $this->reportOnResponse($response, $tokens, (string)$id, true);
            return true;
        } catch (GuzzleException $e) {
            $responseCode = $e->getCode();
            $this->getLog()->error(sprintf('Received response code %d', $responseCode));

            // Special handling of specific response codes
            if ($responseCode === 401) {
                // your API key is bad: don't try to do anything else
                $this->getLog()->error('This response code likely means the API key is incorrect');
                throw new \OutOfBoundsException($e->getMessage(), $e->getCode(), $e);
            }

            if ($responseCode === 429) {
                // Rate limit: raise an exception
                throw new RateLimitException('Digistorm service is rate-limited', 0, $e);
            }

            // Does this have a response? If so report on each token
            if ($e instanceof RequestException) {
                $this->reportOnResponse($e->getResponse(), $tokens, (string)$id, false);
            }

            $this->getLog()->warning($e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Report on the errors (or absence of errors) in the response.
     *
     * @param ResponseInterface $response
     * @param array             $tokens
     * @param string            $id
     * @param bool              $success  Was this successful in the first place?
     * @return void
     */
    private function reportOnResponse(ResponseInterface $response, array $tokens, string $id, bool $success): void
    {
        $responseContent = json_decode($response->getBody()->getContents(), true);
        if (!is_array($responseContent)) {
            $this->getLog()->warning('Response could not be parsed as JSON');
            return;
        }

        // Extract errors from the response content
        $errors = $responseContent['errors'] ?? [];

        // Report on each error in the content
        $seenErrors = [];
        if (is_iterable($errors)) {
            foreach ($errors as $error) {
                $token = $error['id'] ?? $error['username'] ?? null;
                if (isset($token, $error['description'])) {
                    $this->report(
                        "$id@$token",
                        'Error: ' . $error['description'],
                        Logger::NOTICE,
                        '?',
                    );

                    // Don't log any other message for this token
                    $seenErrors[$token] = true;
                }
            }
        }

        // Log messages for tokens not seen in the errors
        foreach ($tokens as $token) {
            if (isset($seenErrors[$token])) {
                continue;
            }
            $reportToken = "$id@$token";
            if ($success) {
                $this->report($reportToken, 'No error', Logger::INFO);
            } else {
                $this->report($reportToken, 'No error, but response itself was an error', Logger::NOTICE, '?');
            }
        }
    }
}
