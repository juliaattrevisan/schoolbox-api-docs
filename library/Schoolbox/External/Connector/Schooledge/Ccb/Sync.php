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
use Schoolbox\Sync\Iterator;

class Sync extends BaseSync
{
    /**
     * CCB's student contact filter also allows through any user who has the
     * custom flag "Copy-of-reports".
     *
     * @var string
     */
    protected $studentContactFilter = "(
        sc.ResidentialGuardian = 'Y' OR
        sc.Relationship = 'Father' OR
        sc.Relationship = 'Mother' OR
        sc.Relationship = 'Stepfather' OR
        sc.Relationship = 'Stepmother' OR
        sc.Relationship = 'Step Father' OR
        sc.Relationship = 'Step Mother' OR
        sc.Relationship = 'Houseparents' OR
        sc.Relationship LIKE '%Guardian%' OR
        sc.Relationship LIKE 'Foster%' OR
        sc.Relationship = 'Carer' OR
        ad.FieldValue = 'Y'
    )";

    /**
     * Join onto CCB's "copy of reports" custom field.
     *
     * (53 is the ID of this field.)
     *
     * @var string
     */
    protected $adhocJoin = "LEFT JOIN AdhocData ad ON ad.AdhocID = 53 AND c.ID = AdhocData.SourceID";

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        $query = "
            SELECT
                ''          AS student_contact_network_login,
                c.Code      AS student_contact_id,
                c.Title     AS student_contact_title,
                c.FirstName AS student_contact_given_name,
                c.FirstName AS student_contact_preferred,
                c.LastName  AS student_contact_surname,
                ''          AS student_contact_home_email,
                CASE WHEN c.EmailCorrespondence = 'Y'
                    THEN c.Email
                    ELSE ''
                END         AS student_contact_default_email,
                ''          AS student_contact_work_email,
                s.Code      AS student_id,
                s.Year      AS student_year_level,
                s.CampusID  AS student_campus,
                s.House     AS student_house,
                s.Sex       AS student_gender,
                s.FirstName AS student_preferred,
                s.LastName  AS student_surname
            FROM Contact c
            INNER JOIN StudentContact sc ON c.ID = sc.ContactID
            INNER JOIN Student         s ON sc.StudentID = s.ID
            {$this->adhocJoin}
            WHERE {$this->studentContactFilter}
            AND   s.DateLeft IS NULL
            ORDER BY c.Code ASC
        ";

        return new Iterator\Guardian(
            $this->databaseExternal->getQueryIterator($query)
        );
    }
}
