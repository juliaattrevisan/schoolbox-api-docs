<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\Test;

use LogicException;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\Warning;
use function debug_backtrace;

/**
 * This class is being used due to phpunit.xml
 * @noinspection PhpUnused
 */
/**
 * A custom phpunit listener to crash code-coverage reporting when a broken code-coverage annotation is encoutered
 *
 * @see        https://github.com/paratestphp/paratest/issues/282
 * @deprecated This is to compensate for a known issue in paratest/phpunit/codecoverage combination.
 *             The proper solution is to upgrade paratest/phpunit
 */
class MakeWarningErrorAgainTestListener implements TestListener
{
    use TestListenerDefaultImplementation;

    /**
     * @inheritdoc
     * @throws LogicException
     */
    public function addWarning(Test $test, Warning $e, $time): void
    {
        $backTrace = debug_backtrace();
        $caller    = $backTrace[1];

        // Yes, this is a fragile hack.
        //
        // The challenge is that with our current version of paratest, we cannot
        // pass through command line flags to phpunit. So we are constrained to the limited paratest v1.0 options, or
        // global changes if the phpunit.xml file.
        // If we use the latter to hook in a custom listener, it will be used on all phpunit invocations.
        //
        // Also, it appears paratest and phpunit are running in different processes, so we cannot use
        // STDERR or STDOUT in this process to render output to user.
        // Instead, we will have to throw a LogicException which will halt the code-coverage testing.

        if ($caller['class'] === 'PHPUnit\Framework\TestResult' && $caller['line'] === 806) {
            // Did we catch an InvalidCoversTargetException on specific PhpUnit version?
            throw new LogicException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
