<?php
/**
 * Iterator for a list of results from `External_Isams_Batch_Reader_Student->parse()`.
 *
 * Prevents traversal further down the tree than the Pupil node.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Isams_Batch_Reader_Iterator_Student_Filter extends RecursiveArrayIterator
{
    /**
     * @return bool
     */
    public function hasChildren()
    {
        $current = $this->current();
        return is_array($current)
            && (!(array_key_exists('name', $current) && $current['name'] == '{}Pupil'));
    }
}
