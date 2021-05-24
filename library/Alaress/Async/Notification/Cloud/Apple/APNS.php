<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Notification\Cloud\Apple;

use Alaress\Async\Notification\Cloud\AbstractService;
use InvalidArgumentException;
use Monolog\Logger;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;
use ZendService\Apple\Apns\Client\Message as Client;
use ZendService\Apple\Apns\Response\Message as Response;
use ZendService\Apple\Apns\Message;
use ZendService\Apple\Exception\RuntimeException;

/**
 * Apple Push Notification Service
 *
 * Class APNS
 *
 * @package Alaress\Async\Notification\Cloud\Apple
 */
class APNS extends AbstractService
{

    /** @var Client */
    protected $client;

    /** @var int */
    protected $environment;

    /** @var string */
    protected $certPath;

    /** @var string */
    protected $passPhrase;


    /**
     * @param RegistryMapper $registryMapper
     * @param string         $appIdentifier
     * @param int            $environment
     * @param string         $certificatePath
     * @param string         $passPhrase
     */
    public function __construct(RegistryMapper $registryMapper, $appIdentifier, $environment, $certificatePath, $passPhrase = null)
    {
        parent::__construct($registryMapper, $appIdentifier);
        $this->client      = new Client();
        $this->environment = $environment;
        $this->certPath    = $certificatePath;
        $this->passPhrase  = $passPhrase;
    }


    /**
     * @return string
     */
    public function getProviderName()
    {
        return Registry::PROVIDER_APPLE;
    }


    /**
     * @param array  $tokens
     * @param int    $id
     * @param string $title
     * @param int    $unreadCount
     * @return bool
     */
    public function propagate(array $tokens, $id, $title, $unreadCount = 0)
    {
        $alert = new Message\Alert();

        $alert->setBody($title);

        $message = new Message();

        $message->setId($id);
        $message->setBadge($unreadCount);
        $message->setAlert($alert);
        $message->setSound('default');

        foreach ($tokens as $token) {
            try {
                $message->setToken($token);
            } catch (InvalidArgumentException $e) {
                // your token is not a hex string
                $this->getLog()->debug("Invalid token: '$token': removing from registry");
                $this->deleteToken($token);
                continue;
            }

            try {
                // ensure there's a connection here, then push the message
                $this->ensureConnection();

                $rs     = $this->client->send($message);
                $id     = strlen($rs->getId()) ? $rs->getId() : '?';
                $prefix = "$id@$token";

                switch ($rs->getCode()) {
                    case Response::RESULT_OK:
                        $this->report($prefix, "All ok!", Logger::DEBUG);
                        break;
                    case Response::RESULT_PROCESSING_ERROR:
                        // you may want to retry
                        $this->report($prefix, "Processing error", Logger::INFO, '?');
                        break;
                    case Response::RESULT_MISSING_TOKEN:
                        // you were missing a token
                        $this->report($prefix, "Missing token", Logger::INFO, '?');
                        break;
                    case Response::RESULT_MISSING_TOPIC:
                        // you are missing a message id
                        $this->report($prefix, "Missing topic", Logger::INFO, '?');
                        break;
                    case Response::RESULT_MISSING_PAYLOAD:
                        // you need to send a payload
                        $this->report($prefix, "Missing payload", Logger::INFO, '?');
                        break;
                    case Response::RESULT_INVALID_TOKEN_SIZE:
                        // the token provided was not of the proper size
                        $this->report($prefix, "Invalid token size", Logger::INFO, '?');
                        break;
                    case Response::RESULT_INVALID_TOPIC_SIZE:
                        // the topic was too long
                        $this->report($prefix, "Invalid topic size", Logger::INFO, '?');
                        break;
                    case Response::RESULT_INVALID_PAYLOAD_SIZE:
                        // the payload was too large
                        $this->report($prefix, "Invalid payload size", Logger::INFO, '?');
                        break;
                    case Response::RESULT_INVALID_TOKEN:
                        // the token was invalid; remove it from your system
                        $this->report($prefix, "Invalid token", Logger::INFO, '?');
                        // we need to delete this token, it will only break things
                        // TODO remove the singletonness...
                        $this->deleteToken($token);
                        $this->client->close();
                        break;
                    case Response::RESULT_UNKNOWN_ERROR:
                        // apple didn't tell us what happened
                        $this->report($prefix, "No idea what went wrong", Logger::NOTICE, '?');
                        break;
                }
            } catch (InvalidArgumentException $e) {
                // unable to connect to APNS, abort mission
                $this->getLog()->error("Connection to APNS failed: {$e->getMessage()}", [ 'exception' => $e ]);
                return false;
            } catch (RuntimeException $e) {
                // connected, but server became unavailable
                $this->getLog()->error("APNS server stopped responding: {$e->getMessage()}", [ 'exception' => $e ]);
                return false;
            }
        }

        // we're done here
        $this->client->close();

        return true;
    }


    /**
     * @return void
     */
    protected function ensureConnection()
    {
        if (!$this->client->isConnected()) {
            $this->client->open($this->environment, $this->certPath, $this->passPhrase);
        }
    }
}
