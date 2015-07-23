<?php
/**
 * XML reader for finding staff contacts in iSAMS' batch API response.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Sabre\Xml\Reader;

class External_Isams_Batch_Reader_Staff extends Reader
{
    /**
     * Teachers are in /HRManager/CurrentStaff/StaffMember
     *
     * @var array
     */
    public $elementMap = [
        '{}StaffMember' => Sabre\Xml\Element\KeyValue::class,
    ];


    /**
     * Gets the pupils from whatever XML document was set into this reader.
     *
     * @return Traversable
     * @throws \Sabre\Xml\LibXMLException
     */
    public function getAccountInfo()
    {
        return new CallbackFilterIterator(
            new External_Isams_Batch_Reader_Iterator_Staff_Map(
                new CallbackFilterIterator(
                    new RecursiveIteratorIterator(
                        new External_Isams_Batch_Reader_Iterator_Staff_Filter($this->parse())
                    ),
                    function ($value) {
                        return is_array($value)
                            && array_key_exists('name', $value)
                            && $value['name'] == '{}StaffMember';
                    }
                )
            ),
            function ($value) {
                return is_array($value)
                && array_key_exists('RecordType', $value)
                && $value['RecordType'] == 'StaffMember';
            }
        );
    }
}
