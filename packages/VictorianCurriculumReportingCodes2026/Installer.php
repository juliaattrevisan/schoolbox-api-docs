<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace VictorianCurriculumReportingCodes2026;

use Schoolbox\Admin\Package;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer extends Package\IBCurriculum
{
    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/CurriculumExport--Victorian_Curriculum_Progression_Reports__Secondary.csv';
}
