<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Cloud\Firebase;

use Alaress\Async\Notification\Cloud\AbstractService;
use Alaress\Async\Notification\Cloud\RateLimitException;
use Alaress\Async\Notification\Notification;
use Kreait\Firebase;
use Monolog\Logger;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;
use SensitiveParameter;

/**
 * Firebase-based notification service.
 */
class Service extends AbstractService
{
    /**
     * Messaging client: created only once, and only when required.
     */
    private ?Firebase\Contract\Messaging $messagingClient = null;

    /**
     * Constructor
     *
     * @param RegistryMapper $registryMapper Mapper for fetching registrations.
     * @param string         $appIdentifier  Identifying string for the app: matches `mobile_app.app_identifier`.
     * @param string         $privateKey     Private key for Firebase's service: matches `mobile_app.key`.
     *                                       See https://firebase-php.readthedocs.io/en/stable/setup.html for how to get
     *                                       a private key.
     * @param string         $appTitle       Title of the app: matches `config.title`.
     */
    public function __construct(
        RegistryMapper $registryMapper,
        $appIdentifier,
        #[SensitiveParameter] private string $privateKey,
        private string $appTitle,
    ) {
        parent::__construct($registryMapper, $appIdentifier);
    }


    /**
     * @param array      $tokens
     * @param int        $id
     * @param string     $title
     * @param int        $unreadCount
     * @param null|Notification $notification
     * @inheritDoc
     */
    protected function propagate(array $tokens, $id, $title, $unreadCount = 0, ?Notification $notification = null, ?string $recipientExternalId = null)
    {
        // Create messaging client
        $this->messagingClient ??= (new Firebase\Factory())
            ->withServiceAccount($this->privateKey)
            ->createMessaging();

        // Create message
        $config = Firebase\Messaging\WebPushConfig::fromArray([
            'notification' => [
                'title' => $this->appTitle,
                'body' => $title,
                'icon' => $notification->getIcon(),
                'timestamp' => $notification->getTimestamp(),
                'href'      => $notification->getHref(),
                'unread'    => $unreadCount,
                'meta'      => json_encode($notification->getMeta())
            ],
            'fcm_options' => [
                'link' => $notification->getHref(),
            ],
        ]);
        $message = Firebase\Messaging\CloudMessage::new()->withWebPushConfig($config);

        // Try to send it to all recipients
        $report = null;
        try {
            $report = $this->messagingClient->sendMulticast($message, $tokens);
        } catch (Firebase\Exception\InvalidArgumentException $e) {
            // Invalid message or empty token list
            $this->getLog()->warning('Invalid message: ' . $e->getMessage());
        } catch (Firebase\Exception\Messaging\QuotaExceeded $e) {
            // This may be a rate limit: handle that especially
            throw new RateLimitException('Firebase service is rate-limited', 0, $e, $e->retryAfter());
        } catch (Firebase\Exception\MessagingException $e) {
            // API request failed
            $this->getLog()->warning('Firebase API request failed: ' . $e->getMessage());
        } catch (Firebase\Exception\FirebaseException $e) {
            // Something unexpected happened
            $this->getLog()->warning('Unexpected error: ' . $e->getMessage());
        }
        if (!$report) {
            return false;
        }

        // Iterate and log report
        foreach ($report->getItems() as $item) {
            $token       = $item->target()->value();
            $reportToken = "$id@$token";
            $error       = $item->error()?->getMessage();
            if ($item->messageWasInvalid()) {
                $status   = 'Invalid message';
                $severity = Logger::NOTICE;
            } elseif ($item->messageTargetWasInvalid()) {
                $status   = 'Invalid message target';
                $severity = Logger::NOTICE;
            } elseif ($item->messageWasSentToUnknownToken()) {
                $status   = 'Unknown recipient';
                $severity = Logger::NOTICE;
            } elseif ($item->isFailure()) {
                $status   = 'Unknown error';
                $severity = Logger::NOTICE;
            } else {
                // Presumed valid
                $status   = 'OK';
                $severity = Logger::INFO;
            }
            if (isset($error)) {
                $status .= ': ' . $error;
            }
            $this->report($reportToken, $status, $severity);
        }

        return !$report->hasFailures();
    }

    /**
     * @inheritDoc
     */
    public function getProviderName()
    {
        return Registry::PROVIDER_FIREBASE;
    }
}
