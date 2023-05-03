<?php

namespace AusPersonalDevelopmentBadge;

use Schoolbox\Admin\Package;

/**
 * Script for installing a badge CSV file into the badge table
 */
class Installer extends Package\BaseBadges implements Package\IPackageInstall
{
    /**
     * @inheritdoc
     */
    protected array $requiredPackages = ['AusGeneralCapabilities'];

    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/badge.csv';
}
