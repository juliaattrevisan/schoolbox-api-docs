<?php

/**
 * Upload
 *
 * Asynchronous daemon uploader for crocodoc
 *
 * This allows crocodoc-friendly material to be transferred asynchronously
 * using a daemon, rather than forcing people to upload files to crocodoc
 * inline in the browser, thus enduring a perhaps-terrible wait
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\InlineGrader\Asynchronous;

use \Psr\Log\LoggerInterface;
use Schoolbox\Learning\InlineGrader\Authenticator;

class Upload
{

    /**
     * @var Authenticator
     */
    private $authenticator;

    /**
     * A logger
     * 
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Upload constructor.
     * @param Authenticator $authenticator
     * @param LoggerInterface $logger
     */
    public function __construct(Authenticator $authenticator, LoggerInterface $logger)
    {
        $this->setAuthenticator($authenticator);
        $this->setLogger($logger);
    }

    /**
     * Find files that can be put into crocodoc and transmit them asynchronously
     */
    public function uploadPendingCrocodocs()
    {

    }

    /**
     * @return Authenticator
     */
    private function getAuthenticator()
    {
        return $this->authenticator;
    }

    /**
     * @param Authenticator $authenticator
     * @return $this
     */
    private function setAuthenticator($authenticator)
    {
        $this->authenticator = $authenticator;
        return $this;
    }

    /**
     * @return LoggerInterface
     */
    private function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     * @return $this
     */
    private function setLogger($logger)
    {
        $this->logger = $logger;

        return $this;
    }
}