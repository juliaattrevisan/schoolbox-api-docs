<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Reporting\Decorator;

use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\Learning\Assessment\Reporting\Model;

/**
 * Class Decorator
 *
 * @package Schoolbox\Learning\Assessment\Reporting\Decorator
 */
class Decorator implements DecoratorInterface
{
    use ReportingTrait;


    /**
     * Decorator constructor.
     */
    public function __construct(
    ) {
    }


    /**
     * @param Model $assessment
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function decorate($assessment): void
    {
    }
}
