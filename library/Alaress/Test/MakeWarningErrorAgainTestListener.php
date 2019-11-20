<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\Test;

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\TestResult;
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
     */
    public function addWarning(Test $test, Warning $e, $time): void
    {
        $backTrace = debug_backtrace();
        $caller    = $backTrace[1];

        // Yes, this is a fragile hack.
        //
        // If called from TestResult, promote this warning to an error.
        // Proper fix is to upgrade paratest + phpunit

        $object = $caller['object'];
        if ($object instanceof TestResult) {
            $object->addError($test, $e, $time);
        }
    }
}
