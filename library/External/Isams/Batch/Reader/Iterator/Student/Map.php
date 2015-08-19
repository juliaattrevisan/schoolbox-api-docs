<?php
/**
 * Maps results for `External_Isams_Batch_Reader_Student->parse()`.
 *
 * Converts results to a mixture of:
 * `External_UserInterface->getStudentUserAccountInfo()` and
 * `External_UserInterface->getStudent()` returns.
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
                'StudentBirthDate'     => "$result->DOB",
                'StudentForm'          => "$result->Form",
            ];
        } elseif ($current->matches('//CurrentPupilsHealth/Pupil')) {
            return [
                'RecordType'          => 'CurrentPupilsHealth',
                'ID'                  => $id,
                'StudentMedicalAlert' => "$result->HealthNotes",
            ];
        } elseif ($current->matches('//SchoolManager/Years/Year')) {
            return [
                'RecordType' => 'Year',
                'ID'         => $id,
                'YearLevel'  => "$result->NationalCurriculumYear",
                'YearName'   => "$result->Name",
                'YearCode'   => "$result->Code",
            ];
        } elseif ($current->matches('//SchoolManager/SchoolDivisions/SchoolDivision')) {
            return [
                'RecordType'       => 'SchoolDivision',
                'ID'               => $id,
                'CampusName'       => "$result->Name",
                'CampusCode'       => "$result->Code",
                'CampusYearLevels' => $this->parseCampusYears($result->Years[0]),
            ];
        } else {
            return [
                'RecordType' => 'Unknown',
                'ID'         => $id,
            ];
        }
    }

    /**
     * Parses the year list in a SchoolDivision element into a list of year IDs.
     *
     * @param  SimpleXMLElement $years
     * @return array
     */
    protected function parseCampusYears($years)
    {
        $yearIds = [];
        if ($years instanceof SimpleXMLElement) {
            foreach ($years->children() as $year) {
                $yearIds[(int)$year->attributes()->id] = (int)$year->attributes()->YearId;
            }
        }
        return $yearIds;
    }
}
