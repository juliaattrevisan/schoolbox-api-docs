<?php
/**
 * Reduces results for `External_Isams_Batch_Reader_Enrolment->parse()`.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Isams_Batch_Reader_Iterator_Enrolment_Reduce
{
    /**
     * @param array $carry
     * @param array $item
     *
     * @return array
     */
    public function __invoke($carry, $item)
    {
        if ($item['RecordType'] === 'Room') {
            // Rooms: store in a separate array
            $roomId                              = $item['ID'];
            $carry['rooms'][$roomId]['RoomCode'] = $item['RoomCode'];
        } elseif ($item['RecordType'] === 'Subject') {
            // Subject name and code: store in a separate array
            $subjectId = $item['ID'];
            $carry['subjects'][$subjectId]['SubjectName'] = $item['SubjectName'];
            $carry['subjects'][$subjectId]['SubjectCode'] = $item['SubjectCode'];
        } elseif ($item['RecordType'] === 'Set') {
            $setId = $item['ID'];
            if (!array_key_exists($setId, $carry['classes'])) {
                $carry['classes'][$setId] = [
                    'ClassStudents' => [],
                    'ClassPeriods'  => [],
                ];
            }
            $carry['classes'][$setId]['ID']            = $setId;
            $carry['classes'][$setId]['RoomID']        = $item['RoomID'];
            $carry['classes'][$setId]['SubjectID']     = $item['SubjectID'];
            $carry['classes'][$setId]['ClassYear']     = $item['ClassYear'];
            $carry['classes'][$setId]['ClassName']     = $item['ClassName'];
            $carry['classes'][$setId]['ClassCode']     = $item['ClassCode'];
            $carry['classes'][$setId]['ClassTeachers'] = $item['ClassTeacherIds'];
        } elseif ($item['RecordType'] === 'SetList') {
            $setId = $item['SetID'];
            if (!array_key_exists($setId, $carry['classes'])) {
                $carry['classes'][$setId] = [
                    'ClassStudents' => [],
                    'ClassPeriods'  => [],
                ];
            }
            $carry['classes'][$setId]['ClassStudents'][] = $item['StudentID'];
        } elseif ($item['RecordType'] === 'Schedule') {
            // Schedule stores only a class code, not a class ID.
            // For that reason the Set records need to be parsed before Schedule.
            // TODO write this into a separate map/reduce process
            foreach ($carry['classes'] as $setId => $carryItem) {
                if ($item['ClassCode'] === $carry['classes'][$setId]['ClassCode']) {
                    $carry['classes'][$setId]['ClassPeriods'][] = [
                        'ClassPeriodID' => $item['ClassPeriodID'],
                        'ClassRoomID'   => $item['ClassRoomID'],
                    ];
                    break;
                }
            }
        }
        return $carry;
    }
}
