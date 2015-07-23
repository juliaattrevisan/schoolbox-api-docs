<?php
/**
 * XML reader for finding parent contacts in iSAMS' batch API response.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Sabre\Xml\Reader;

class External_Isams_Batch_Reader_Parent extends External_Isams_Batch_Reader_Base
{
    public $elementMap = [
        '{}Contact'      => Sabre\Xml\Element\KeyValue::class,
        '{}Address'      => Sabre\Xml\Element\KeyValue::class,
        '{}AddressLines' => Sabre\Xml\Element\KeyValue::class,
        '{}Pupil'        => Sabre\Xml\Element\KeyValue::class,
    ];


    /**
     * Gets the pupils from whatever XML document was set into this reader.
     *
     * @return Traversable
     * @throws \Sabre\Xml\LibXMLException
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
