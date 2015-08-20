<?php
/**
 * XML reader for finding class enrolments in iSAMS' batch API response.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Isams_Batch_Reader_Enrolment extends External_Isams_Batch_Reader_Base
{
    /**
     * Gets the pupils from whatever XML document was set into this reader.
     *
     * @return Iterator
     */
    public function getAccountInfo()
    {
        // There's no way to reduce an iterator politely
        $reducer = new External_Isams_Batch_Reader_Iterator_Enrolment_Reduce();
        $result  = ['subjects' => [], 'classes' => [], 'rooms' => [],];
        foreach (
            new External_Isams_Batch_Reader_Iterator_Enrolment_Map(
                new \Alaress\Xml\Filter\Path(
                    $this->iterator,
                    [
                        '//TeachingManager/Departments/Department/Subjects/Subject',
                        '//SchoolManager/Forms/Form',
                        '//PupilManager/CurrentPupils/Pupil',
                        '//TeachingManager/Sets/Set',
                        '//TeachingManager/SetLists/SetList',
                        '//TimetableManager/PublishedTimetables/Timetable/Schedules/Schedule',
                        '//EstateManager/Buildings/Building/Classrooms/Classroom',
                    ]
                )
            ) as $row
        ) {
            $result = $reducer($result, $row);
        }
        // Merge the class name into the subject
        $actualResult = [];
        foreach ($result['classes'] as $class) {
            if ($result['subjects'][$class['SubjectID']]['SubjectName']) {
                $class['ClassName'] = $result['subjects'][$class['SubjectID']]['SubjectName'];
            }

            $actualClassPeriods = [];
            foreach ($class['ClassPeriods'] as $classPeriod) {
                $classPeriod['ClassRoomCode'] = $result['rooms'][$classPeriod['ClassRoomID']]['RoomCode'];
                $actualClassPeriods[]         = $classPeriod;
            }
            $class['ClassPeriods'] = $actualClassPeriods;
            $actualResult[] = $class;
        }
        return new ArrayIterator($actualResult);
    }
}
