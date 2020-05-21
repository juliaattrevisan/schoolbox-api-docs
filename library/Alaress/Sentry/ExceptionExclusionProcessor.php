<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry;

use Raven_Client;
use Raven_Processor;

/**
 * Excludes particular exception classes.
 */
class ExceptionExclusionProcessor extends Raven_Processor
{
    /**
     * Do not raise exceptions for these classes.
     *
     * @var array|string[]
     */
    private $excludedExceptions;


    /**
     * Constructor
     *
     * @param Raven_Client $client
     * @param string       ...$excludedExceptions
     */
    public function __construct(Raven_Client $client, string ...$excludedExceptions)
    {
        $this->excludedExceptions = array_map([$this, 'normalizeClassName'], $excludedExceptions);
        parent::__construct($client);
    }


    /**
     * If the exception in $data matches a an excluded exception, don't send to
     * Sentry.
     *
     * @param array &$data
     */
    public function process(&$data)
    {
        foreach (($data['exception']['values'] ?? []) as $i => $value) {
            $type = $this->normalizeClassName($value['type'] ?? '');
            foreach ($this->excludedExceptions as $excludedExceptionClass) {
                if (is_a($type, $excludedExceptionClass) || is_subclass_of($type, $excludedExceptionClass)) {
                    unset($data['exception']['values'][$i]);
                    break;
                }
            }
        }

        // Are no exceptions at all left?
        // Empty out data, thus causing Sentry to (hopefully) bin this message.
        if (empty($data['exception']['values'])) {
            $data = [];
        }
    }


    /**
     * Raven passes class names with no trailing backslash: PHP ::class
     * constants do. Normalize for comparison.
     *
     * @param string $className
     * @return string
     */
    private function normalizeClassName(string $className): string
    {
        return ltrim($className, '\\');
    }
}
