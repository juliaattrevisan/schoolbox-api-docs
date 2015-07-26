<?php
/**
 * XML reader for finding class enrolments in iSAMS' batch API response.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Isams_Batch_Reader_Enrolment extends External_Isams_Batch_Reader_Base
{
    /**
     * Gets the pupils from whatever XML document was set into this reader.
     *
     * @return Iterator
     */
    public function getAccountInfo()
    {
        // There's no way to reduce an iterator politely
        $reducer = new External_Isams_Batch_Reader_Iterator_Enrolment_Reduce();
        $result  = [];
        foreach (
            new External_Isams_Batch_Reader_Iterator_Enrolment_Map(
                new \Alaress\Xml\Filter\Path(
                    $this->iterator,
                    [
                        '//TeachingManager/Sets/Set',
                        '//TeachingManager/SetLists/SetList',
                        '//TimetableManager/PublishedTimetable/Timetable/Schedules/Schedule',
                    ]
                )
            ) as $row
        ) {
            $result = $reducer($result, $row);
        }
        return new ArrayIterator($result);
    }
}
