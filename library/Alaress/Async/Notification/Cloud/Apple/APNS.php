<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Cloud\Apple;

use Alaress\Async\Notification\Cloud\ServiceInterface;

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
class APNS implements ServiceInterface
{

    /** @var Client */
    protected $client;

    /** @var string */
    protected $uri;

    /** @var string */
    protected $certPath;

    /** @var string */
    protected $passPhrase;


    /**
     * @param string $uri
     * @param string $certificatePath
     * @param string $passPhrase
     */
    public function __construct($uri, $certificatePath, $passPhrase = null)
    {
        $this->client     = new Client();
        $this->uri        = $uri;
        $this->certPath   = $certificatePath;
        $this->passPhrase = $passPhrase;
    }


    /**
     * @return string
     */
    public function getProviderName()
    {
        return 'apns';
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
            $message->setId(substr(sha1("$token.".json_encode($data)), 0, 4));
            $message->setToken($token);
            $message->setBadge(null);
            $message->setSound('bingbong.aiff');

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
            $this->client->open($this->uri, $this->certPath, $this->passPhrase);
        }
    }

} 