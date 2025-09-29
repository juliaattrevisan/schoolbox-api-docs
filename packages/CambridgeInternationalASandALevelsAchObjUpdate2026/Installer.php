<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace CambridgeInternationalASandALevelsAchObjUpdate2026;

use Schoolbox\Admin\Package;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer extends Package\BaseCurriculum
{
    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/CurriculumExportCambridgeASandALevelsAchObjUpdate.csv';
}
