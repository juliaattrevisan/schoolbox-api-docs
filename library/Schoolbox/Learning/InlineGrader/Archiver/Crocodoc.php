<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\InlineGrader\Archiver;

use Alaress\Database\Connector\IConnector;
use Guzzle\Http\Client as HttpClient;
use Psr\Log\LoggerInterface;

/**
 * Archival tool for Crocodoc documents.
 */
class Crocodoc
{
    /**
     * @var IConnector
     */
    private $database;

    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * Build the archiver.
     *
     * @param IConnector      $database
     * @param HttpClient      $httpClient
     * @param LoggerInterface $log
     */
    public function __construct(IConnector $database, HttpClient $httpClient, LoggerInterface $log)
    {
        $this->database   = $database;
        $this->httpClient = $httpClient;
        $this->log        = $log;
    }
}
