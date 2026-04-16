<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport\Strategy\Mark;

use Schoolbox\Learning\Assessment\IAssessable;

interface IStrategy
{
    /**
     * Calculate the (normalised mark, learner human readable mark, instructor human readable mark) tuple
     * via the dominant discriminator column $column
     *
     * @param array $row
     * @param IAssessable $assessment
     * @return array
     */
    public function calculate(array $row, IAssessable $assessment): array;


    /**
     * These columns are all we care about in this csv file
     *
     * @return string[]
     */
    public function getExpectedColumns(): array;
}
