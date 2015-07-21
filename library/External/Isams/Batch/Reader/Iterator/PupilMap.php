<?php
/**
 * Maps results for `External_Isams_Batch_Reader_CurrentPupil->parse()`.
 *
 * Prevents traversal further down the tree than the Pupil node.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Isams_Batch_Reader_Iterator_PupilMap extends IteratorIterator
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
        $result  = ['PupilId' => $current['attributes']['id']];
        foreach ($current['value'] as $key => $value) {
            $result[preg_replace('/^{.*?}/', '', $key)] = $value;
        }
        return $result;
    }
}
