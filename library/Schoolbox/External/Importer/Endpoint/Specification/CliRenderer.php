<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint\Specification;

use Psr\Log\LoggerInterface;

/**
 * Class CliRenderer
 *
 * Render endpoint statuses into a cli-friendly format
 */
class CliRenderer
{

    /**
     * Status text for existing nodes
     */
    public const STATUS_OK = 'OK';


    /**
     * Status text for missing nodes
     */
    public const STATUS_MISSING = 'MISSING';


    /**
     * Status text for existing nodes that are empty but probably should contain something
     */
    public const STATUS_EMPTY = 'EMPTY';


    /**
     * Number of spaces for output formatting
     */
    protected const PADDING = 10;


    /**
     * @var LoggerInterface
     */
    protected $log;


    /**
     * CliRenderer constructor.
     *
     * @param LoggerInterface|null $log
     */
    public function __construct(?LoggerInterface $log)
    {
        $this->log = $log;
    }


    /**
     * Render into a cli-friendly format
     *
     * @param array $endpoints
     */
    public function render(array $endpoints): void
    {
        $messages = [];

        /* Find the longest message and pad it out
         */
        $paddedLength = max(array_map('\strlen', array_column($endpoints, 'message'))) + self::PADDING;

        foreach ($endpoints as $endpoint) {
            $status  = str_pad($endpoint['message'], $paddedLength);
            $message = "{$status} {$endpoint['status']}";

            /* Format log messages
             */
            if ($this->log) {
                switch ($endpoint['status']) {
                    case self::STATUS_OK:
                        $this->log->info($message);
                        break;

                    case self::STATUS_MISSING:
                    case self::STATUS_EMPTY:
                    default:
                        $this->log->warning($message);
                        break;
                }
            }
        }
    }
}
