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
 * Processor for the Sentry client, used for preventing particular classes of
 * exception from being reported to Sentry.
 */
class ExceptionExclusionProcessor extends Raven_Processor
{
    /**
     * Do not report to Sentry for exceptions that are of these classes or their
     * subclasses.
     *
     * @var array|string[]
     */
    private $excludedClasses;


    /**
     * Constructor
     *
     * @param Raven_Client $client
     * @param string       ...$excludedClasses
     */
    public function __construct(Raven_Client $client, string ...$excludedClasses)
    {
        $this->excludedClasses = array_map([$this, 'normalizeClassName'], $excludedClasses);
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
            if (in_array($type, $this->excludedClasses, true)) {
                unset($data['exception']['values'][$i]);
                continue;
            }
            foreach ($this->excludedClasses as $excludedClass) {
                if (is_a($type, $excludedClass) || is_subclass_of($type, $excludedClass)) {
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
