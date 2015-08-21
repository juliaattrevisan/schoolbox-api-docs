<?php
/**
 * Reduces results for `External_Isams_Batch_Reader_Timetable->parse()`.
 *
 * Merges timetable day results for the same campus into a single record.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Isams_Batch_Reader_Iterator_Timetable_Reduce
{
    /**
     * @param array $carry
     * @param array $item
     *
     * @return array
     */
    public function __invoke($carry, $item)
    {
        if ($item['RecordType'] === 'Week') {
            $campus = $item['TimetableCampusName'];
            if (!isset($carry[$campus])) {
                $carry[$campus] = $item;
            } else {
                $carry[$campus]['TimetableDays'] = array_merge(
                    $carry[$campus]['TimetableDays'],
                    $item['TimetableDays']
                );
            }

        }
        return $carry;
    }
}
