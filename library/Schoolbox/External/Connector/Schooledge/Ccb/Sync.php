<?php
/**
 * Schooledge implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Schooledge\Ccb;

use Schoolbox\External\Connector\Schooledge\Base\Sync as BaseSync;

class Sync extends BaseSync
{
    /**
     * Builds a filter for a student's contacts.
     *
     * This filters out anyone who's not a primary guardian or parent, but
     * allows users who have a
     *
     * @return string
     */
    protected function buildStudentContactFilter()
    {
        return parent::buildStudentContactFilter() . "
            OR ad.FieldValue = 'Y'
        ";
    }

    /**
     * Builds a set of joins onto the AdHoc table.
     *
     * This implementation joins onto ad-hoc field 53 ("Copy of reports").
     *
     * @return string
     */
    protected function buildStudentContactAdhocJoins()
    {
        return 'LEFT JOIN AdhocData ad ON ad.AdhocID = 53 AND c.ID = ad.SourceID';
    }
}
