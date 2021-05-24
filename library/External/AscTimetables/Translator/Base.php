<?php

/**
 * Base translator from ASC Timetables XML export to Schoolbox CSV timetable
 * import.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

abstract class External_AscTimetables_Translator_Base
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
     * Gets a list of results from whatever XML document was set into this reader.
     *
     * @return array[]
     */
    abstract public function translateXmlToArray();
}
