<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace IBProgramme;

use Schoolbox\Admin\Package;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer extends Package\BaseCurriculum implements Package\IPackageInstall
{
    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/ibgeneral.csv';
}
