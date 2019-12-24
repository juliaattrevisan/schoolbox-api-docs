<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Class Specification
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint
 */
abstract class Specification
{
    /**
     * Status text for existing nodes
     */
    protected const STATUS_OK = 'OK';


    /**
     * Status text for missing nodes
     */
    protected const STATUS_MISSING = 'MISSING';


    /**
     * Number of spaces for output formatting
     */
    protected const PADDING = 10;


    /**
     * @var LoggerInterface
     */
    protected $log;


    /**
     * @var Batchxml
     */
    protected $batchxml;


    /**
     * @var array
     */
    protected $paths = [];


    /**
     * Specification constructor.
     *
     * @param LoggerInterface|null $log
     * @param Batchxml             $batchxml
     */
    public function __construct(?LoggerInterface $log, Batchxml $batchxml)
    {
        $this->log = $log;
        $this->batchxml = $batchxml;
    }


    /**
     * Get a list of nodes that did, or didn't, get configured properly
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function check(): array
    {
        $messages = [];

        /* No paths, no trabajo
         */
        if (!\count($this->paths)) {
            return $messages;
        }

        $paddedLength = max(array_map('\strlen', $this->paths)) + self::PADDING;

        foreach ($this->paths as $path) {
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
