<?php

/**
 * Iterator over a XMLReader
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Xml;

use XmlReader;

class ReaderIterator implements \Iterator
{
    /**
     * The stack of elements.
     *
     * @var array
     */
    protected $elementStack = [];

    /**
     * The number of elements
     *
     * @var int
     */
    protected $elementCount = 0;

    /**
     * The XML reader, with whatever its internal state is
     *
     * @var XMLReader
     */
    protected $reader;

    /**
     * Constructor
     *
     * @param XmlReader $reader
     */
    public function __construct(XMLReader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * Move forward to next element
     *
     * @return void
     */
    public function next(): void
    {
        $this->elementCount++;

        // Pop this node name off the stack if I'm done with it
        if ($this->reader->nodeType === XMLReader::ELEMENT) {
            if ($this->reader->isEmptyElement) {
                array_pop($this->elementStack);
            }
        } elseif ($this->reader->nodeType === XMLReader::END_ELEMENT) {
            array_pop($this->elementStack);
        }
    }

    /**
     * Return the current element
     *
     * @return State Current state of $this->reader
     */
    public function current(): mixed
    {
        // Push this node name on the stack for navigation
        if ($this->reader->nodeType === XMLReader::ELEMENT) {
            array_push($this->elementStack, $this->reader->name);
        }
        return new State($this->reader, $this->elementStack);
    }

    /**
     * Return the key of the current element
     *
     * @return int
     */
    public function key(): mixed
    {
        return $this->elementCount;
    }

    /**
     * Checks if current position is valid
     *
     * @return bool
     */
    public function valid(): bool
    {
        return $this->reader->read();
    }

    /**
     * This isn't rewindable, the XMLReader isn't even cloneable
     *
     * @return void
     */
    public function rewind(): void
    {
    }
}
