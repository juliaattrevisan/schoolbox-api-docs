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
     * @param array $tokens
     * @param array $data
     * @return bool
     */
    public function send(array $tokens, array $data)
    {
        $message = new Message();

        foreach ($tokens as $token) {
            try {
                $message->setId(substr(sha1("$token.".json_encode($data)), 0, 4));
                $message->setToken($token);
                $message->setBadge(null);
                $message->setSound('bingbong.aiff');

            } catch (InvalidArgumentException $e) {

                // TODO log invalid something something
                continue;
            }


            try {
                $this->ensureConnection();
                $responseCode = $this->client->send($message)->getCode();

            } catch (RuntimeException $e) {
                // TODO use log
                echo $e;

                $responseCode = 10;
            }

            if ($responseCode == 10) {
                $this->client->close();
            }
        }
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
