<?php
/**
 * XML reader for finding parent contacts in iSAMS' batch API response.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Isams_Batch_Reader_Parent extends External_Isams_Batch_Reader_Base
{
    /**
     * Gets the parents from whatever XML document was set into this reader.
     *
     * @return Iterator
     */
    public function getAccountInfo()
    {
        return new External_Isams_Batch_Reader_Iterator_Parent_Map(
            new \Alaress\Xml\Filter\Path(
                $this->iterator,
                '//PupilManager/Contacts/Contact'
            )
        );
    }
}
