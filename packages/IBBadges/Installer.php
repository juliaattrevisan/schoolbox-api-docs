<?php

namespace IBBadges;

use Schoolbox\Admin\Package;

/**
 * Script for installing a badge CSV file into the badge table
 */
class Installer extends Package\BaseBadges implements Package\IPackageInstall
{
    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/badge.csv';

    /**
     * @inheritdoc
     */
    protected array $requiredPackages = ['IBProgramme', 'IBMYPProgramme'];
}
