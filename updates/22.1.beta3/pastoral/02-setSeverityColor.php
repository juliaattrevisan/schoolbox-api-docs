#!/usr/bin/php
<?php

declare(strict_types=1);

use Alaress\Color\ColorFactory;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Set colours to pastoral severity
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $countQuery = $this->getDatabaseInternal()->prepareQuery(
            "SELECT `id` FROM `lu_pastoral_severity` WHERE `deleted_at` IS NULL ORDER BY `sequence`"
        );
        $countQuery->execute();
        $severityIds = $countQuery->fetchAll(\PDO::FETCH_COLUMN);
        $count       = count($severityIds);

        if ($count === 0) {
            return;
        }

        $updateQuery  = $this->getDatabaseInternal()->prepareQuery(
            "UPDATE `lu_pastoral_severity` SET `color` = :color WHERE `id` = :id"
        );
        $colorFactory = new ColorFactory();
        $green        = $colorFactory->build('#008000');
        $red          = $colorFactory->build('#ff0000');

        $greenHsl = $green->getAsHslArray();
        $redHsl   = $red->getAsHslArray();

        $gHue   = $greenHsl['hue'];
        $gSat   = $greenHsl['saturation'];
        $gLight = $greenHsl['lightness'];

        $rHue   = $redHsl['hue'];
        $rSat   = $redHsl['saturation'];
        $rLight = $redHsl['lightness'];

        $dHue   = ($rHue - $gHue) / ($count - 1);
        $dSat   = ($rSat - $gSat) / ($count - 1);
        $dLight = ($rLight - $gLight) / ($count - 1);

        foreach ($severityIds as $i => $severityId) {
            $newColor = \SSNepenthe\ColorUtils\hsl(
                $gHue + $i * $dHue,
                $gSat + $i * $dSat,
                $gLight + $i * $dLight,
            );
            $updateQuery->execute(['color' => $newColor->getRgb()->toHexString(), 'id' => $severityId]);
        }
    }

    /**
     * @inheritDoc
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
