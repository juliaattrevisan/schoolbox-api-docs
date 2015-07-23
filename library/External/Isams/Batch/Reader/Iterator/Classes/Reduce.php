<?php
/**
 * Reduces results for `External_Isams_Batch_Reader_Classes->parse()`.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Isams_Batch_Reader_Iterator_Classes_Reduce
{
    /**
     * @param array $carry
     * @param array $item
     *
     * @return array
     */
    public function __invoke($carry, $item)
    {
        if ($item['RecordType'] === 'Subject') {
            $subjectId = $item['ID'];
            if (!array_key_exists($subjectId, $carry)) {
                $carry[$subjectId] = [];
            }
            $carry[$subjectId]['ID']        = $subjectId;
            $carry[$subjectId]['ClassName'] = $item['ClassName'];
        } elseif ($item['RecordType'] === 'Set') {
            $subjectId = $item['ClassID'];
            if (!array_key_exists($subjectId, $carry)) {
                $carry[$subjectId] = [];
            }
            $carry[$subjectId]['ID']              = $subjectId;
            $carry[$subjectId]['ClassYear']       = $item['ClassYear'];
            $carry[$subjectId]['ClassCode']       = $item['ClassCode'];
            $carry[$subjectId]['ClassTeacherIds'] = $item['ClassTeacherIds'];
        }
        return $carry;
    }
}
