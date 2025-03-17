<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace IBTransdisciplinary;

use Schoolbox\Admin\Package;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer extends Package\BaseCurriculum
{
    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/IBPYPTransDis2025.csv';
}
