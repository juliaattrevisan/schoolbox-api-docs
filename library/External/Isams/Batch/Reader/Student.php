<?php
/**
 * XML reader for finding current students in iSAMS' batch API response.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Sabre\Xml\Reader;

class External_Isams_Batch_Reader_Student extends Reader
{
    public $elementMap = [
        '{}Pupil' => Sabre\Xml\Element\KeyValue::class,
    ];


    /**
     * Gets the pupils from whatever XML document was set into this reader.
     *
     * @return Traversable
     * @throws \Sabre\Xml\LibXMLException
     */
    public function getPupils()
    {
        // So what's with iterator chain?
        // * External_Isams_Batch_Reader_Iterator_Student_Filter stops iterating below Pupil
        // * RecursiveIteratorIterator flattens recursive iterator
        // * CallbackFilterIterator removes all non-pupils from return
        // * External_Isams_Batch_Reader_Iterator_Student_Map formats results nicely
        return new External_Isams_Batch_Reader_Iterator_Student_Map(
            new CallbackFilterIterator(
                new RecursiveIteratorIterator(
                    new External_Isams_Batch_Reader_Iterator_Student_Filter($this->parse())
                ),
                function ($value) {
                    return is_array($value)
                        && array_key_exists('name', $value)
                        && $value['name'] == '{}Pupil';
                }
            )
        );
    }
}
