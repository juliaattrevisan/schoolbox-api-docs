<?php
/**
 * Synergetic implementation of sync connector for Donvale
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Donvale;

use Schoolbox\External\Connector\Synergetic\Base\Sync as BaseSync;
use Schoolbox\Sync\Iterator;

class Sync extends BaseSync
{
    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $query = "

            SELECT DISTINCT student_contact_network_login, 
            student_contact_id, 
            student_contact_title, 
            student_contact_title, 
            student_contact_given_name, 
            student_contact_preferred,
            student_contact_surname,
            student_contact_default_email,
            student_contact_work_email,
            student_id, 
            student_campus, 
            CASE WHEN ISNULL(student_year_level,'') = '' THEN 0 ELSE student_year_level END AS student_year_level,
            student_gender,
            student_preferred,
            student_surname
            FROM dbo.uvSchoolBox_Parents;
        ";

        return new Iterator\Guardian(
            $this->databaseExternal->getQueryIterator($query)
        );
    }
}
