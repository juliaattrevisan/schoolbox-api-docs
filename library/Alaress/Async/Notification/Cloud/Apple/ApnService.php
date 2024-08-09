<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Cloud\Apple;

use Alaress\Async\Notification\Cloud\AbstractService;
use Apple\ApnPush\Certificate\Certificate;
use Apple\ApnPush\Exception\CertificateFileNotFoundException;
use Apple\ApnPush\Model\Alert;
use Apple\ApnPush\Model\Aps;
use Apple\ApnPush\Model\DeviceToken;
use Apple\ApnPush\Model\Notification;
use Apple\ApnPush\Model\Payload;
use Apple\ApnPush\Model\Receiver;
use Apple\ApnPush\Protocol\Http\Authenticator\CertificateAuthenticator;
use Apple\ApnPush\Protocol\ProtocolInterface;
use Apple\ApnPush\Sender\Builder\Http20Builder;
use Apple\ApnPush\Sender\Sender;
use Monolog\Logger;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;

/**
 * Abstraction for sending messages via the APN (Apple Push Notification)
 * service.
 */
class ApnService extends AbstractService
{
    /**
     * APN protocol, if created.
     *
     * (Created automatically on calling `$this->propagate`.)
     */
    private ?ProtocolInterface $protocol;

    /**
     * The app's title, usually from `config.title`.
     */
    protected string $appTitle;

    /**
     * The path to the certificate required to send via APN.
     */
    protected string $certPath;

    /**
     * The passphrase for `$this->certPath`.
     */
    protected string $passPhrase;

    /**
     * Constructor
     *
     * @param RegistryMapper $registryMapper
     * @param string         $appIdentifier
     * @param string         $appTitle
     * @param string         $certPath
     * @param string|null    $passPhrase
     */
    public function __construct(
        RegistryMapper $registryMapper,
        string $appIdentifier,
        string $appTitle,
        string $certPath,
        string $passPhrase = null
    ) {
        parent::__construct($registryMapper, $appIdentifier);
        $this->appTitle   = $appTitle;
        $this->certPath   = $certPath;
        $this->passPhrase = $passPhrase;
    }

    /**
     * Destructor: close the connection if it was created.
     */
    public function __destruct()
    {
        if ($this->protocol) {
            try {
                $this->protocol->closeConnection();
            } catch (\TypeError $e) {
                // probably already closed
            }
        }
    }

    /**
     * @return string
     */
    public function getProviderName(): string
    {
        return Registry::PROVIDER_APPLE;
    }

    /**
     * @inheritdoc
     */
    public function propagate(array $tokens, $id, $title, $unreadCount = 0, ?\Alaress\Async\Notification\Notification $notification = null, ?string $recipientExternalId = null): bool
    {
        // Try to set up the protocol
        try {
            $this->protocol ??= $this->buildProtocol();
        } catch (CertificateFileNotFoundException $e) {
            $this->getLog()->error("APNS server stopped responding: {$e->getMessage()}", ['exception' => $e]);
            return false;
        }

        // Build the sender
        $sender = new Sender($this->protocol);

        // Build the message
        $alert        = new Alert($title);
        $aps          = (new Aps($alert))
            ->withBadge($unreadCount)
            ->withSound('default')
            ->withThreadId((string)$id);
        $payload      = new Payload($aps);
        $notification = new Notification($payload);

        // Send the message to everyone
        foreach ($tokens as $token) {
            $receiver = new Receiver(new DeviceToken($token), $this->getAppIdentifier());
            $prefix   = "$id@$token";
            try {
                $sender->send($receiver, $notification);
                $this->report($prefix, "All ok!", Logger::INFO);
            } catch (\Exception $e) {
                // Exception in doc block is SendNotificationException,
                // but the actual exception thrown is HttpSenderException...
                $this->report($prefix, 'Error: ' . $e->getMessage(), Logger::NOTICE, '?');
            }
        }
        return true;
    }

    /**
     * Builds the APN protocol.
     *
     * @return ProtocolInterface
     * @throws CertificateFileNotFoundException
     */
    private function buildProtocol(): ProtocolInterface
    {
        $certificate   = new Certificate($this->certPath, $this->passPhrase);
        $authenticator = new CertificateAuthenticator($certificate);
        $builder       = new Http20Builder($authenticator);
        return $builder->buildProtocol();
    }
}
