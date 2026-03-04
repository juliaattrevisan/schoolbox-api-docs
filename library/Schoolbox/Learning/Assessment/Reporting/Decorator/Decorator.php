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
        private ?int $markType,
        private ?int $instructorMarkType,
        private ?int $gradingScheme,
        private ?int $instructorGradingScheme,
        private ?float $maxMark,
        private ?float $instructorMaxMark,
        private ?bool $supportingDataEnable,
        private ?array $supportingDataDateRange,
        private ?array $supportingDataFolders,
        private ?array $supportingDataWorkTypes,
        private ?bool $supportingDataMegamarkbook,
        private ?bool $feedbackCommentsEnable
    ) {
    }


    /**
     * Assign values to an $assessment instance, basically hydrating it
     *
     * @param Model $assessment
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function decorate($assessment): void
    {

    }
}
