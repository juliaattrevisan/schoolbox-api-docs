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
    protected $adhocJoin = "LEFT JOIN AdhocData ad ON ad.AdhocID = 53 AND c.ID = ad.SourceID";

    /**
     * @inheritDoc
     */
    public function getEnrolledStudents($externalUserId)
    {
        $query = "
            SELECT
                s.Code AS RelatedID
            FROM Student s
            LEFT JOIN StudentContact sc ON s.ID = sc.StudentID
            LEFT JOIN Contact c ON sc.ContactID = c.ID
            {$this->adhocJoin}
            WHERE c.Code = {$this->getDatabase()->smartQuote($externalUserId)}
            AND {$this->studentContactFilter}
        ";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * @inheritDoc
     */
    public function getParentEmails($synergyIds)
    {
        if (empty($synergyIds)) {
            return [];
        }

        $idString = implode(',', array_map([$this->getDatabase(), 'smartQuote'], $synergyIds));

        $query = "
            SELECT
                s.Code AS StudentID,
                c.Code AS ContactID,
                c.Email
            FROM Contact c
            INNER JOIN StudentContact sc ON c.ID = sc.ContactID
            INNER JOIN Student s ON sc.StudentID = s.ID
            {$this->adhocJoin}
            WHERE s.Code IN ($idString)
            AND {$this->studentContactFilter}
        ";

        $result = [];
        foreach ($this->getDatabase()->getAll($query) as $row) {
            $result[$row['StudentID'].'-'.$row['ContactID']] = $row['Email'];
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getStudentContactList($externalUserId)
    {
        $query = "
            SELECT
                c.Code AS StudentContactID,
                c.Title AS StudentContactTitle,
                c.FirstName AS StudentContactPreferred,
                c.LastName AS StudentContactSurname,
                c.Email AS StudentContactDefaultEmail,
                c.PhoneHome AS StudentContactPhoneActual,
                0 AS StudentContactSilentPhoneFlag,
                c.PhoneMobile AS StudentContactMobilePhone,
                c.PhoneWork AS StudentContactOccupPhone,
                '' AS StudentContactRelation,
                -- No speese
                '' AS StudentContactSpouseID,
                '' AS StudentContactSpouseTitle,
                '' AS StudentContactSpousePreferred,
                '' AS StudentContactSpouseSurname,
                '' AS StudentContactSpouseMobilePhone,
                '' AS StudentContactSpouseOccupPhone,
                '' AS StudentContactSpouseDefaultEmail,
                '' AS StudentContactType,
                '' AS StudentContactSpouseRelation
            FROM Contact c
            INNER JOIN StudentContact sc ON c.ID = sc.ContactID
            INNER JOIN Student s ON sc.StudentID = s.ID
            -- LEFT JOIN ContactFamily cf ON c.ID = cf.ContactID
            -- LEFT JOIN Family f ON cf.FamilyID = f.ID
            LEFT JOIN vw_Contact_Addresses ca ON c.ID = ca.ContactID
            {$this->adhocJoin}
            WHERE s.Code = {$this->getDatabase()->smartQuote($externalUserId)}
            AND
            (
              sc.ResidentialGuardian = 'Y' OR
              sc.PrimaryMailing = 'Y' OR
              (sc.FeeFactor<>0 AND sc.EmergencyContact = 'Y' AND sc.Relationship <> 'Grandmother')
            )
            AND ca.MailAddrID = ca.ID
            AND {$this->studentContactFilter}
        ";
        return $this->getDatabase()->getAll($query);
    }

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
