<?php

namespace CambridgeIGCSE25;

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
    protected string $sourceFile = __DIR__ . '/CambridgeCurric25.csv';
}
