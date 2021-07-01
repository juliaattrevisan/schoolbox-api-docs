<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\mtc;

use Alaress\Database\Predicate\Builder;

/**
 * Responsible for providing the link between a Canvas Course and a Schoolbox Folder+UnitSpec.
 */
class Course extends \External\Canvas\Sync\Bridge\Course
{
    /**
     * @inheritDoc
     */
    public function getCanvasCoursesToImport(string $courseAlias = 'c', string $sectionAlias = 'cs'): Builder
    {
        // TODO: how to determine term?
        $pb = (new Builder($this->canvasDb->getQuoter(), ''))
//            ->in('term.sis_source_id', ['2019Whole', '2020Whole', '2020TRANS'])
            ->greaterThan('term.date_start', '2019-11-23')
            ->in("{$courseAlias}.workflow_state", ['available', 'completed']);
        return (new Builder($this->canvasDb->getQuoter(), ''))
            ->all($pb);
    }
}
