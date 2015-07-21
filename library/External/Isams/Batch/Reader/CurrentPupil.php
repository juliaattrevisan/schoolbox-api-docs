<?php
/**
 * XML reader for finding current pupils in iSAMS' batch API response.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Sabre\Xml\Reader;

class External_Isams_Batch_Reader_CurrentPupil extends Reader
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
        // * External_Isams_Batch_Reader_Iterator_Pupil filters stops iterating below Pupil
        // * RecursiveIteratorIterator flattens recursive iterator
        // * CallbackFilterIterator removes all non-pupils from return
        return new CallbackFilterIterator(
            new RecursiveIteratorIterator(
                new External_Isams_Batch_Reader_Iterator_Pupil($this->parse())
            ),
            function ($value) {
                return $value['name'] == '{}Pupil';
            }
        );
    }
}
