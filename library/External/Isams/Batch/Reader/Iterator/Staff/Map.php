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
        $current = $this->getInnerIterator()->current();
        $result  = new SimpleXMLElement($current->getReader()->readOuterXml());
        $id      = (string)$result->attributes()->id;
        if ($current->matches('//HRManager/CurrentStaff/StaffMember')) {
            return [
                'RecordType'         => 'StaffMember',
                'ID'                 => $id,
                // getStaffUserAccountInfo fields
                'StaffID'            => "$result->UserCode",
                'NetworkLogin'       => "$result->UserName",
                'ActiveFlag'         => true,
                'StaffTitle'         => "$result->Title",
                'StaffPreferredName' => "$result->Forename",
                'StaffSurname'       => "$result->Surname",
                'Email'              => "$result->SchoolEmailAddress",
                'OccupEmail'         => "$result->SchoolEmailAddress",
            ];
        } else {
            return [
                'RecordType' => 'Unknown',
                'ID'         => $id,
            ];
        }
    }
}
