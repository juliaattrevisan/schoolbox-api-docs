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
                $carry[$setId] = ['ClassStudents' => [],];
            }
            $carry[$setId]['ID']            = $setId;
            $carry[$setId]['ClassYear']     = $item['ClassYear'];
            $carry[$setId]['ClassCode']     = $item['ClassCode'];
            $carry[$setId]['ClassTeachers'] = $item['ClassTeacherIds'];
        } elseif ($item['RecordType'] === 'SetList') {
            $setId = $item['SetID'];
            if (!array_key_exists($setId, $carry)) {
                $carry[$setId] = ['ClassStudents' => [],];
            }
            $carry[$setId]['ClassStudents'][] = $item['StudentID'];
        }
        return $carry;
    }
}
