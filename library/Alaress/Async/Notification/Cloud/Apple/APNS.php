<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Cloud\Apple;

use Alaress\Async\Notification\Cloud\AbstractService;

use InvalidArgumentException;
use Schoolbox\MobileApp\Registry;
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
     * @param int    $environment
     * @param string $certificatePath
     * @param string $passPhrase
     */
    public function __construct($environment, $certificatePath, $passPhrase = null)
    {
        parent::__construct();
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
     * @return bool
     */
    public function propagate(array $tokens, $id, $title)
    {
        $alert = new Message\Alert();

        $alert->setBody($title);

        $message = new Message();

        $message->setId($id);
        $message->setBadge(0);
        $message->setAlert($alert);
        $message->setSound('default');

        foreach ($tokens as $token) {
            try {
                $message->setToken($token);

            } catch (InvalidArgumentException $e) {
                // your token is not a hex string
                continue;
            }

            try {
                // ensure there's a connection here, then push the message
                $this->ensureConnection();

                $response = $this->client->send($message);
                $prefix   = "{$response->getId()}@$token";

                switch ($response->getCode()) {
                    case Response::RESULT_OK:
                        $this->getLog()->info("$prefix: All ok!", [ 'char' => '+' ]);
                        break;
                    case Response::RESULT_PROCESSING_ERROR:
                        // you may want to retry
                        $this->getLog()->notice("$prefix: Processing error", [ 'char' => '?' ]);
                        break;
                    case Response::RESULT_MISSING_TOKEN:
                        // you were missing a token
                        $this->getLog()->notice("$prefix: Missing token", [ 'char' => '?' ]);
                        break;
                    case Response::RESULT_MISSING_TOPIC:
                        // you are missing a message id
                        $this->getLog()->notice("$prefix: Missing topic", [ 'char' => '?' ]);
                        break;
                    case Response::RESULT_MISSING_PAYLOAD:
                        // you need to send a payload
                        $this->getLog()->notice("$prefix: Missing payload", [ 'char' => '?' ]);
                        break;
                    case Response::RESULT_INVALID_TOKEN_SIZE:
                        // the token provided was not of the proper size
                        $this->getLog()->notice("$prefix: Invalid token size", [ 'char' => '?' ]);
                        break;
                    case Response::RESULT_INVALID_TOPIC_SIZE:
                        // the topic was too long
                        $this->getLog()->notice("$prefix: Invalid topic size", [ 'char' => '?' ]);
                        break;
                    case Response::RESULT_INVALID_PAYLOAD_SIZE:
                        // the payload was too large
                        $this->getLog()->notice("$prefix: Invalid payload size", [ 'char' => '?' ]);
                        break;
                    case Response::RESULT_INVALID_TOKEN:
                        // the token was invalid; remove it from your system
                        $this->getLog()->notice("$prefix: Invalid token", [ 'char' => '?' ]);
                        break;
                    case Response::RESULT_UNKNOWN_ERROR:
                        // apple didn't tell us what happened
                        $this->getLog()->notice("$prefix: No idea what went wrong", [ 'char' => '?' ]);
                        break;
                }

            } catch (InvalidArgumentException $e) {
                // unable to connect to APNS, abort mission
                $this->getLog()->error("Connection to APNS failed: {$e->getMessage()}", [ $e ]);
                return false;

            } catch (RuntimeException $e) {
                // connected, but server became unavailable
                $this->getLog()->error("APNS server stopped responding: {$e->getMessage()}", [ $e ]);
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
