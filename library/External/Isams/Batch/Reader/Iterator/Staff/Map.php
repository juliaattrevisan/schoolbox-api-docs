<?php
/**
 * Maps results for `External_Isams_Batch_Reader_Staff->parse()`.
 *
 * Converts results to the same format that
 * `External_UserInterface->getStaffUserAccountInfo()` returns.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Isams_Batch_Reader_Iterator_Staff_Map extends External_Isams_Batch_Reader_Iterator_BaseMap
{
    /**
     * @return array
     */
    public function current()
    {
        // StaffID,
        // NetworkLogin,
        // ActiveFlag,
        // StaffTitle,
        // StaffPreferredName,
        // StaffSurname,
        // DefaultEmailCode,
        // OccupEmail,
        // Email,
        // StaffCampus,
        // StaffCategoryType,
        // StaffCategory,
        // StaffDepartment

        $current = $this->getInnerIterator()->current();
        $result  = $this->stripClarkNotationFromArrayKeys($current['value']);

        // ID: the database's PK value, distinct from SchoolID
        $id = $current['attributes']['id'];
        if (array_key_exists('Address', $result)) {
            // //Contacts/Contact: build student's contacts out of this
            // iSAMS contains no home phone
            return [
                'RecordType'         => 'StaffMember',
                'ID'                 => $id,
                // getStaffUserAccountInfo fields
                'StaffID'            => $result['UserCode'],
                'NetworkLogin'       => $result['UserName'],
                'ActiveFlag'         => true,
                'StaffTitle'         => $result['Title'],
                'StaffPreferredName' => $result['Forename'],
                'StaffSurname'       => $result['Surname'],
                'Email'              => $result['SchoolEmailAddress'],
            ];
        } else {
            return [
                'RecordType' => 'Unknown',
                'ID'         => $id,
            ];
        }
    }
}
