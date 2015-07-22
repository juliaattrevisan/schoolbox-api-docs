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
class External_Isams_Batch_Reader_Iterator_Student_Map extends IteratorIterator
{
    /**
     * @return array
     */
    public function current()
    {
        // At this stage I still can't tell if I have:
        // //CurrentPupils/Pupil or //CurrentPupilsHealth/Pupil
        // I don't know where I came from.
        $current = $this->getInnerIterator()->current();
        $result  = [];
        foreach ($current['value'] as $key => $value) {
            $result[preg_replace('/^{.*?}/', '', $key)] = $value;
        }

        // ID: the database's PK value, distinct from SchoolID
        $id = $current['attributes']['id'];
        if (array_key_exists('SchoolCode', $result)) {
            // //CurrentPupils/Pupil: contains SchoolCode
            // don't know StudentCampus yet. Probably in AcademicHouse?
            return [
                'RecordType'           => 'CurrentPupils',
                'ID'                   => $id,
                'StudentID'            => $result['SchoolId'],
                'NetworkLogin'         => $result['UserName'],
                'StudentTitle'         => $result['Title'],
                'StudentPreferred'     => $result['Preferredname'],
                'StudentSurname'       => $result['Surname'],
                'StudentGender'        => $result['Gender'],
                'StudentEmail'         => $result['EmailAddress'],
                'StudentOccupEmail'    => $result['EmailAddress'],
                'StudentYearLevel'     => $result['NCYear'],
                'StudentHouse'         => $result['AcademicHouse'],
                'StudentBoarder'       => $result['PupilType'],
                'StudentBoardingHouse' => $result['BoardingHouse'],
            ];
        } else {
            // //CurrentPupilsHealth/Pupil: doesn't
            // I assume HealthNotes is what should show on the profile
            return [
                'RecordType'          => 'CurrentPupilsHealth',
                'ID'                  => $id,
                'StudentMedicalAlert' => $result['HealthNotes'],
            ];
        }
    }
}
