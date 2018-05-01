<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);


/**
 * Class External_Schooledge_Ccb_User
 */
class External_Schooledge_Ccb_User extends External_Schooledge_User
{
    /**
     * Builds a filter for a student's contacts.
     *
     * This filters out anyone who's not a primary guardian or parent, but
     * allows users who have an ad-hoc field value set to "Y".
     *
     * (The join onto that field is built in `buildStudentContactAdhocJoins`.)
     *
     * @see buildStudentContactAdhocJoins
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
