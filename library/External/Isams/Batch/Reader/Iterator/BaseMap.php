<?php
/**
 * Iterator for a list of results from `External_Isams_Batch_Reader_CurrentPupil->parse()`.
 *
 * Prevents traversal further down the tree than the Pupil node.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Isams_Batch_Reader_Iterator_BaseMap extends IteratorIterator
{
    /**
     * Strips Clark notation from an XML path.
     *
     * @param string $path
     * @return string
     */
    protected function stripClarkNotation($path)
    {
        return preg_replace('/^{.*?}/', '', $path);
    }

    /**
     * Strips Clark notation from each key in an array.
     *
     * @param array $array
     * @return array
     */
    protected function stripClarkNotationFromArrayKeys($array)
    {
        $newArray = [];
        foreach ($array as $key => $value) {
            $newArray[$this->stripClarkNotation($key)] = $value;
        }
        return $newArray;
    }
}
