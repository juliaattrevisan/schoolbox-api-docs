<?php

/**
 * Reduces results for `External_Isams_Batch_Reader_Classes->parse()`.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class External_Isams_Batch_Reader_Iterator_Student_Reduce
{
    /**
     * @param array $carry
     * @param array $item
     *
     * @return array
     */
    public function __invoke($carry, $item)
    {
        $id = $item['ID'];
        if ($item['RecordType'] === 'CurrentPupils' || $item['RecordType'] === 'CurrentPupilsHealth') {
            if (!array_key_exists($id, $carry['students'])) {
                $carry['students'][$id] = [];
            }
            $carry['students'][$id] = array_merge($carry['students'][$id], $item);
        } elseif ($item['RecordType'] === 'Year') {
            $carry['years'][$id] = $item;
        } elseif ($item['RecordType'] === 'SchoolDivision') {
            $carry['campuses'][$id] = $item;
        }
        return $carry;
    }
}
