<?php
/**
 * XML reader for finding current students in iSAMS' batch API response.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Isams_Batch_Reader_Student extends External_Isams_Batch_Reader_Base
{
    /**
     * Gets the students from whatever XML document was set into this reader.
     *
     * @return Iterator
     */
    public function getAccountInfo()
    {
        $reducer = new External_Isams_Batch_Reader_Iterator_Student_Reduce();
        $result  = ['students' => [], 'years' => [],];
        foreach (
            new External_Isams_Batch_Reader_Iterator_Student_Map(
                new \Alaress\Xml\Filter\Path(
                    $this->iterator,
                    [
                        '//CurrentPupils/Pupil',
                        '//CurrentPupilsHealth/Pupil',
                        '//SchoolManager/Years/Year',
                    ]
                )
            ) as $row
        ) {
            $result = $reducer($result, $row);
        }
        // Merge the year level into the student
        $actualResult = [];
        foreach ($result['students'] as $student) {
            $yearLevel = $student['StudentYearLevel'];
            $student['StudentYearLevel'] = $result['years'][$yearLevel]['YearLevel'];
            $student['StudentYearName']  = $result['years'][$yearLevel]['YearName'];
            $student['StudentYearCode']  = $result['years'][$yearLevel]['YearCode'];
            $actualResult[] = $student;
        }
        return new ArrayIterator($actualResult);
    }
}
