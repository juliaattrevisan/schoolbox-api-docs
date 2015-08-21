<?php
/**
 * XML reader for finding timetables in iSAMS' batch API response.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Isams_Batch_Reader_Timetable extends External_Isams_Batch_Reader_Base
{
    /**
     * Gets the timetable from whatever XML document was set into this reader.
     *
     * @return Iterator
     */
    public function getAccountInfo()
    {
        $reducer = new External_Isams_Batch_Reader_Iterator_Timetable_Reduce();
        $result  = [];
        foreach (
            new External_Isams_Batch_Reader_Iterator_Timetable_Map(
                new \Alaress\Xml\Filter\Path(
                    $this->iterator,
                    '//TimetableManager/Structure/Week'
                )
            ) as $row
        ) {
            $result = $reducer($result, $row);
        }
        return $result;
    }
}
