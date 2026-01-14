<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace WesternAustralianAchievementStandards;

use Schoolbox\Admin\Package\BaseCurriculum;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer extends BaseCurriculum
{
    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/CurriculumExport--WA_Achievement_Standards.csv';
}
