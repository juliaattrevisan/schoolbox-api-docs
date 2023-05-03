<?php

namespace EarlyYearsFrameworkCurriculum;

use Schoolbox\Admin\Package;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer extends Package\BaseCurriculum implements Package\IPackageInstall
{
    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/curriculum.csv';
}
