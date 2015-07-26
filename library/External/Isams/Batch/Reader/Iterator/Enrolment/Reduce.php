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
        if ($item['RecordType'] === 'Set') {
            $setId = $item['ID'];
            if (!array_key_exists($setId, $carry)) {
                $carry[$setId] = [
                    'ClassStudents' => [],
                    'ClassPeriods'  => [],
                ];
            }
            $carry[$setId]['ID']            = $setId;
            $carry[$setId]['ClassYear']     = $item['ClassYear'];
            $carry[$setId]['ClassName']     = $item['ClassName'];
            $carry[$setId]['ClassCode']     = $item['ClassCode'];
            $carry[$setId]['ClassTeachers'] = $item['ClassTeacherIds'];
        } elseif ($item['RecordType'] === 'SetList') {
            $setId = $item['SetID'];
            if (!array_key_exists($setId, $carry)) {
                $carry[$setId] = [
                    'ClassStudents' => [],
                    'ClassPeriods'  => [],
                ];
            }
            $carry[$setId]['ClassStudents'][] = $item['StudentID'];
        } elseif ($item['RecordType'] === 'Schedule') {
            // Schedule stores only a class code, not a class ID.
            // For that reason the Set records need to be parsed before Schedule.
            // TODO write this into a separate map/reduce process
            foreach ($carry as $setId => $carryItem) {
                if ($item['ClassCode'] === $carry[$setId]['ClassCode']) {
                    $carry[$setId]['ClassPeriods'][] = [
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
