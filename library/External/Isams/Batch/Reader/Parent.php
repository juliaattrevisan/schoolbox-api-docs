<?php
/**
 * XML reader for finding parent contacts in iSAMS' batch API response.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Sabre\Xml\Reader;

class External_Isams_Batch_Reader_Parent extends Reader
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
        // So what's with iterator chain?
        // * External_Isams_Batch_Reader_Iterator_PupilFilter stops iterating below Pupil
        // * RecursiveIteratorIterator flattens recursive iterator
        // * CallbackFilterIterator removes all non-pupils from return
        // * External_Isams_Batch_Reader_Iterator_PupilMap formats results nicely
        // Also reduce down to a to a single row per student
        return new CallbackFilterIterator(
            new External_Isams_Batch_Reader_Iterator_Parent_Map(
                new CallbackFilterIterator(
                    new RecursiveIteratorIterator(
                        new External_Isams_Batch_Reader_Iterator_Parent_Filter($this->parse())
                    ),
                    function ($value) {
                        return is_array($value)
                            && array_key_exists('name', $value)
                            && $value['name'] == '{}Contact';
                    }
                )
            ),
            function ($value) {
                return is_array($value)
                && array_key_exists('RecordType', $value)
                && $value['RecordType'] == 'Contacts';
            }
        );
    }
}
