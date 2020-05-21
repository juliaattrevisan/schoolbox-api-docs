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
     * @var array
     */
    private $excludedExceptions;


    /**
     * Constructor
     *
     * @param Raven_Client $client
     * @param array        ...$excludedExceptions
     */
    public function __construct(Raven_Client $client, array ...$excludedExceptions)
    {
        parent::__construct($client);
        $this->excludedExceptions = $excludedExceptions;
    }


    /**
     * If the exception in $data matches a an excluded exception, don't send to
     * Sentry.
     *
     * @param array &$data
     */
    public function process(&$data)
    {
        $data;
    }
}
