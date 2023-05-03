<?php

namespace TASCCurriculum;

use Schoolbox\Admin\Package;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer extends Package\BaseCurriculum implements Package\IPackageInstall
{
    /**
     * @inheritdoc
     */
    protected array $requiredPackages = [];

    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/curriculum.csv';

}
