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
        if ($current->matches('//TeachingManager/Sets/Set')) {
            // This class
            return [
                'RecordType'      => 'Set',
                'ID'              => $id,
                'ClassYear'       => (string)$result->attributes()->YearId,
                'ClassID'         => (string)$result->attributes()->SubjectId,
                'ClassCode'       => "$result->SetCode",
                'ClassTeacherIds' => $this->parseTeachers($result->Teachers[0]),
            ];
        } elseif ($current->matches('//TeachingManager/SetLists/SetList')) {
            // Students for this class
            return [
                'RecordType'      => 'SetList',
                'ID'              => $id,
                'SetID'           => (string)$result->attributes()->SetId,
                'StudentID'       => (string)$result->attributes()->PupilId,
            ];
        } elseif ($current->matches('//TimetableManager/PublishedTimetable/Timetable/Schedules/Schedule')) {
            // Period that room is in
            // (This should have a roomId and TeacherId attribute but doesn't)
            return [
                'RecordType'    => 'Schedule',
                'ClassCode'     => "$result->Code",
                'ClassPeriodID' => "$result->PeriodId",
                'ClassRoomID'   => "$result->RoomId",
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
