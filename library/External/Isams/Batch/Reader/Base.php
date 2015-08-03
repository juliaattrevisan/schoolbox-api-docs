<?php
/**
 * XML reader for finding items in iSAMS' batch API response.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

abstract class External_Isams_Batch_Reader_Base
{
    /**
     * @var Iterator
     */
    protected $iterator;

    public function __construct(XMLReader $reader)
    {
        // Whack the reader in an iterator
        $this->iterator = new Alaress\Xml\Filter\Element(
            new Alaress\Xml\ReaderIterator($reader)
        );
    }

    /**
     * Gets the pupils from whatever XML document was set into this reader.
     *
     * @return Iterator
     */
    abstract public function getAccountInfo();
}
