<?php

namespace ExtraCurricularBadge;

use Schoolbox\Admin\Package;

/**
 * Script for installing a badge CSV file into the badge table
 */
class Installer extends Package\BaseBadges
{
    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/badge.csv';
}
