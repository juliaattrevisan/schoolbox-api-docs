<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Exporter;

use Schoolbox\Assessment\Rubric\Grid\Grid;

/**
 * Exporter for converting a rubric grid to an array.
 */
class ArrayExporter
{
    /**
     * Exports this rubric grid as an array.
     *
     * Whether the export has mark-per-capability or use mark range switched on
     * depends on whether the grid's assessment has these fields switched on.
     *
     * @param Grid $grid
     * @return array
     */
    public function export(Grid $grid): array
    {
        //
    }
}
