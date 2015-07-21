<?php
/**
 * Maps results for `External_Isams_Batch_Reader_Student->parse()`.
 *
 * Converts results to a format that is understandable by Schoolbox sync scripts.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Isams_Batch_Reader_Iterator_Student_Map extends IteratorIterator
{
    /**
     * @return bool
     */
    public function current()
    {
        // At this stage I still can't tell if I have:
        // //CurrentPupils/Pupil or //CurrentPupilsHealth/Pupil
        // I don't know where I came from.
        $current = $this->getInnerIterator()->current();
        $result  = ['StudentId' => $current['attributes']['id']];
        foreach ($current['value'] as $key => $value) {
            $result[preg_replace('/^{.*?}/', '', $key)] = $value;
        }
        return $result;
    }
}
