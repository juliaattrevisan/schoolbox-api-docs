<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace IBDPCore;

use Schoolbox\Admin\Package;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer extends Package\IBCurriculum
{
    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/IBDPCore.csv';
}
