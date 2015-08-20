<?php
/**
 * Maps results for `External_Isams_Batch_Reader_Enrolment->parse()`.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Isams_Batch_Reader_Iterator_Enrolment_Map extends External_Isams_Batch_Reader_Iterator_BaseMap
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
        if ($current->matches('//TeachingManager/Departments/Department/Subjects/Subject')) {
            return [
                'RecordType'  => 'Subject',
                'ID'          => $id,
                'SubjectName' => "$result->Name",
                'SubjectCode' => "$result->Code",
            ];
        } elseif ($current->matches('//TeachingManager/Sets/Set')) {
            // This class
            return [
                'RecordType'      => 'Set',
                'ID'              => $id,
                'SubjectID'       => (string)$result->attributes()->SubjectId,
                'ClassYear'       => (string)$result->attributes()->YearId,
                'ClassCode'       => "$result->SetCode",
                'ClassName'       => "$result->Name",
                'ClassTeacherIds' => $this->parseTeachers($result->Teachers[0]),
            ];
        } elseif ($current->matches('//SchoolManager/Forms/Form')) {
            // A form group - treat this like a class
            // Does this mess up my method for saving classes?
            return [
                'RecordType'      => 'Set',
                'ID'              => $id,
                'SubjectID'       => $id,
                'ClassYear'       => (string)$result->attributes()->YearId,
                'ClassCode'       => "$result->Form",
                'ClassName'       => "Advisory Group (HSAG)",
                'ClassTeacherIds' => [(string)$result->attributes()->TutorId],
            ];
        } elseif ($current->matches('//TeachingManager/SetLists/SetList')) {
            // Students for this class
            return [
                'RecordType'      => 'SetList',
                'ID'              => $id,
                'SetID'           => (string)$result->attributes()->SetId,
                'StudentID'       => (string)$result->attributes()->PupilId,
            ];
        } elseif ($current->matches('//PupilManager/CurrentPupils/Pupil')) {
            // Students for a form class - treat as a set list
            return [
                'RecordType'      => 'SetList',
                'ID'              => $id,
                'SetID'           => (string)$result->Form,
                'StudentID'       => $id,
            ];
        } elseif ($current->matches('//TimetableManager/PublishedTimetables/Timetable/Schedules/Schedule')) {
            // Period that room is in
            // (This should have a roomId and TeacherId attribute but doesn't)
            // Formroom classes have got "HSAG/" prefix
            $code = "$result->Code";
            if (preg_match('|^HSAG/(.*)|', $code, $matches)) {
                $code = $matches[1];
            }
            return [
                'RecordType'    => 'Schedule',
                'ClassCode'     => $code,
                'ClassPeriodID' => "$result->PeriodId",
                'ClassRoomID'   => "$result->RoomId",
            ];
        } elseif ($current->matches('//EstateManager/Buildings/Building/Classrooms/Classroom')) {
            // Room - get a room name from an ID.
            return [
                'RecordType' => 'Room',
                'ID'         => $id,
                'RoomCode'   => "$result->Name",
            ];
        } else {
            return [
                'RecordType'   => 'Unknown',
                'ID'           => $id,
            ];
        }
    }

    /**
     * Parses the teachers in an Teacher element.
     *
     * @param  SimpleXMLElement $teachers
     * @return array
     */
    protected function parseTeachers($teachers)
    {
        $ids = [];
        if ($teachers instanceof SimpleXMLElement) {
            foreach ($teachers->children() as $teacher) {
                $ids[] = (string)$teacher->attributes()->StaffId;
            }
        }
        return $ids;
    }
}
