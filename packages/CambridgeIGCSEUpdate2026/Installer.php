<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace CambridgeIGCSEUpdate2026;

use Schoolbox\Admin\Package;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer extends Package\BaseCurriculum
{
    /**
     * @inheritdoc
     */
    protected bool $extendParent = true;
    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/CambridgeIGCSEUpdate2026.csv';
}
