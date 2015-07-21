<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Isams_Batch_Reader_Iterator_Pupil extends RecursiveArrayIterator
{
    public function hasChildren()
    {
        $current = $this->current();
        return is_array($current)
//            && array_key_exists('name', $current)
            && $current['name'] != '{}Pupil';
    }
}
