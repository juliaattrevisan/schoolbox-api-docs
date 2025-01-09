<?php

/**
 * Schoolbox implementation of timetable connector
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\ITimetable;

class Timetable extends Base implements ITimetable
{
    /**
     * @inheritdoc
     */
    public function getCurrentSemester($externalId = null)
    {
        if (!empty($externalId)) {
            // Get user specific file semester

            $semesterStatement = $this->databaseExternal->executeStoredProcedure(
                'spsConfigUserDefaultYearSemester',
                ['ID', $externalId]
            );

            if ($row = $semesterStatement->fetch()) {
                return $row['UserFileSemester'];
            }
        }

        // Get current default file semester

        $semesterStatement = $this->databaseExternal->executeQuery("
            SELECT    FileSemester
            FROM      FileSemesters
            WHERE     SystemCurrentFlag = 1
        ");

        if ($row = $semesterStatement->fetch()) {
            return $row['FileSemester'];
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getCurrentYear($externalId = null)
    {
        if (!empty($externalId)) {
            // Get user specific file year

            $semesterStatement = $this->databaseExternal->executeStoredProcedure(
                'spsConfigUserDefaultYearSemester',
                ['ID', $externalId]
            );

            if ($row = $semesterStatement->fetch()) {
                return $row['UserFileYear'];
            }
        }

        // Get current default file year

        $semesterStatement = $this->databaseExternal->executeQuery("
            SELECT    FileYear
            FROM      FileSemesters
            WHERE     SystemCurrentFlag = 1
        ");

        if ($row = $semesterStatement->fetch()) {
            return $row['FileYear'];
        }

        return null;
    }
}
