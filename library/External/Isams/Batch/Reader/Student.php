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
     * Gets the pupils from whatever XML document was set into this reader.
     *
     * @return Iterator
     */
    public function getAccountInfo()
    {
        return new External_Isams_Batch_Reader_Iterator_Student_Map(
            new \Alaress\Xml\Filter\Path(
                $this->iterator,
                '//Pupil'
            )
        );
    }
}
