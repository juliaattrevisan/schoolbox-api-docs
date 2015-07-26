<?php
/**
 * Maps results for `External_Isams_Batch_Reader_Student->parse()`.
 *
 * Converts results to the same format that
 * `External_UserInterface->getStudentUserAccountInfo()` returns.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Isams_Batch_Reader_Iterator_Student_Map extends External_Isams_Batch_Reader_Iterator_BaseMap
{
    /**
     * @return array
     */
    public function current()
    {
        /** @var Alaress\Xml\State $current */
        $current = $this->getInnerIterator()->current();
        $result  = new SimpleXMLElement($current->getReader()->readOuterXml());
        $id      = (string)$result->attributes()->id;
        if ($current->matches('//CurrentPupils/Pupil')) {
            return [
                'RecordType'           => 'CurrentPupils',
                'ID'                   => $id,
                'StudentID'            => "$result->SchoolId",
                'NetworkLogin'         => "$result->UserName",
                'StudentTitle'         => "$result->Title",
                'StudentPreferred'     => "$result->Preferredname",
                'StudentSurname'       => "$result->Surname",
                'StudentGender'        => "$result->Gender",
                'StudentEmail'         => "$result->EmailAddress",
                'StudentOccupEmail'    => "$result->EmailAddress",
                'StudentYearLevel'     => "$result->NCYear",
                'StudentHouse'         => "$result->AcademicHouse",
                'StudentBoarder'       => "$result->PupilType",
                'StudentBoardingHouse' => "$result->BoardingHouse",
            ];
        } elseif ($current->matches('//CurrentPupilsHealth/Pupil')) {
            return [
                'RecordType'          => 'CurrentPupilsHealth',
                'ID'                  => $id,
                'StudentMedicalAlert' => "$result->HealthNotes",
            ];
        } else {
            return [
                'RecordType' => 'Unknown',
                'ID'         => $id,
            ];
        }
    }
}
