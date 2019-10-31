<?php
/**
 *
 * @author      James Leckie <jleckie@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);


class External_Isams_Cis_User extends External_Isams_User
{
    /**
     * @inheritdoc
     */
    public function getStudentContactList(array $externalUserId): array
    {
        $query = "
            SELECT      
                        V_Parent.ParentID             AS StudentContactID,
                        ''                            AS StudentContactTitle,
                        V_Parent.ParentFirstname      AS StudentContactPreferred,
                        V_Parent.ParentSurname        AS StudentContactSurname,
                        V_Parent.CISEmail             AS StudentContactDefaultEmail,
                        1                             AS ValidAddressFlag,
                        V_Parent.Address1             AS StudentContactAddress1,
                        V_Parent.Address2             AS StudentContactAddress2,
                        V_Parent.Address3             AS StudentcontactAddress3,
                        ''                            AS StudentContactSuburb,
                        V_Parent.PostCode             AS StudentContactPostCode,
                        ''                            AS StudentContactState,
                        V_Parent.Country              AS StudentContactCountryCode,
                        V_Parent.Mobile               AS StudentContactMobilePhone,
                        ''                            AS StudentContactOccupPhone,
                        ''                            AS StudentContactPhoneActual,
                        V_Parent.Relationship         AS StudentContactRelation,
                        ''                            AS StudentContactSpouseID,
                        ''                            AS StudentContactSpouseTitle,
                        ''                            AS StudentContactSpousePreferred,
                        ''                            AS StudentContactSpouseSurname,
                        ''                            AS StudentContactSpouseMobilePhone,
                        ''                            AS StudentContactSpouseOccupPhone, 
                        ''                            AS StudentContactSpouseDefaultEmail
            FROM        V_Student
            JOIN        V_Parent ON V_Parent.StudentID = V_Student.StudentID 
            WHERE       V_Student.StudentID = :external_user_id
        ";

        return $this->getConnector()->executeQuery($query , ['external_user_id' => $externalUserId])->fetchAll();
    }
}
