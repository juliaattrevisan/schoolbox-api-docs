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
     * @param LoggerInterface $log
     */
    public function __construct(LoggerInterface $log)
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

        /* Exit early if nothing to do
         */
        if (!\count($endpoints)) {
            $this->log->critical('No endpoints found.');

            return;
        }

        /* Find the longest message and pad it out
         */
        $paddedLength = max(array_map('\strlen', array_column($endpoints, 'message'))) + self::PADDING;

        foreach ($endpoints as $endpoint) {
            $status  = str_pad($endpoint['message'], $paddedLength);
            $message = "{$status} {$endpoint['status']}";

            /* Format log messages
             */
            switch ($endpoint['status']) {
                case ISpecification::STATUS_OK:
                    $this->log->info($message);
                    break;

                case ISpecification::STATUS_MISSING:
                case ISpecification::STATUS_EMPTY:
                default:
                    $this->log->warning($message);
                    break;
            }
        }
    }
}
