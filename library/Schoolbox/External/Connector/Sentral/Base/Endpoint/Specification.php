<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Exception\InvalidUriPartException;

/**
 * Class Specification
 *
 * This explores an endpoint output to check whether certain nodes exist
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
class Specification
{

    /**
     * Status text for existing nodes
     */
    private const STATUS_OK = 'OK';


    /**
     * Status text for missing nodes
     */
    private const STATUS_MISSING = 'MISSING';


    /**
     * Number of spaces for output formatting
     */
    private const PADDING = 20;


    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * Specification constructor.
     *
     * @param LoggerInterface|null $log
     */
    public function __construct(?LoggerInterface $log)
    {
        $this->log = $log;
    }


    /**
     * Get a list of nodes that did, or didn't, get configured properly
     *
     * @return array
     *
     * @throws \RuntimeException
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     */
    public function check(): array
    {
        $paths = [

            /**
             * Student Profile nodes
             */


            /**
             * Parent Profile nodes
             */


            /**
             * Staff Profile nodes
             */


            /**
             * Timetable nodes
             */

        ];

        $paddedLength = max(array_map('\strlen', $paths)) + self::PADDING;

        $messages = [];

        foreach ($paths as $path) {
            $paddedPath = str_pad($path, $paddedLength);

            if ($this->batchxml->fetchXml($path)) {
                $message = "{$paddedPath} " . self::STATUS_OK;

                if ($this->log) {
                    $this->log->info($message);
                }
            } else {
                $message = "{$paddedPath} " . self::STATUS_MISSING;

                if ($this->log) {
                    $this->log->warning($message);
                }
            }

            $messages[] = $message;
        }

        return $messages;
    }
}
