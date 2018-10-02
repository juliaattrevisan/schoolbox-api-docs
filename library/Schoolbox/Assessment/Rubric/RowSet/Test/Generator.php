<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet\Test;

use Schoolbox\Assessment\Rubric\RowSet as RubricRowSet;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Class Generator
 */
class Generator
{

    /**
     * Build a row set.
     *
     * @param array $capabilities
     *
     * @return RubricRowSet\RowSet
     */
    public function generateRowSet(array $capabilities): RubricRowSet\RowSet
    {
        // Build the row set
        $folder = new FolderModel();
        $rowSet = new RubricRowSet\RowSet($folder);

        // Build caps and indicators
        foreach ($capabilities as $capabilityId => $capability) {
            $capabilityObject = (new RubricRowSet\Capability())
                ->setId($capabilityId)
                ->setName($capability['name']);
            foreach ($capability['indicators'] as $indicatorId => $indicator) {
                $indicatorObject = (new RubricRowSet\Indicator())
                    ->setId($indicatorId)
                    ->setName($indicator['name']);
                $capabilityObject->getIndicators()[$indicatorId] = $indicatorObject;
            }

            $rowSet->getCapabilities()[$capabilityId] = $capabilityObject;
        }

        return $rowSet;
    }
}
