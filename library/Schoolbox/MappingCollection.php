<?php
/**
 * Collection intended for column->property mappings for a mapper class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;
//use function array_key_exists;
use BadMethodCallException;
use CallbackFilterIterator;

/**
 * TODO - this probably shouldn't extend Collection anymore, as many Collection methods will
 *        break this class's internals. (it relies on being indexed by property)
 */
class MappingCollection extends Collection
{

    /**
     * Reverse map of columns to properties for efficient by-column lookup
     *
     * If the column is a reference to a joined table, it is prefixed with the join name, eg:
     * * Column on this mapper's table:   "column"
     * * Column on joined mapper's table: "join.column"
     *
     * @var string[]
     */
//    private $columnToPropertyMap = [];

	/**
	 * Retrieves an iterator which filters out any element in the collection
	 * which doesn't pass $this->accept().
	 *
	 * @return \Traversable
	 */
	public function getIterator() {
		return new CallbackFilterIterator(parent::getIterator(), array($this, 'accept'));
	}

	/**
	 * Filters out disabled keys from the mapping.
	 *
	 * * Keys without an "enabled" field default to being enabled
	 * * Keys with a callback as the enabled field are enabled if callback
	 *   returns true
	 * * Keys with any other "enabled" field are enabled if that field == true
	 *
	 * @param array     $current
	 * @param int       $key
	 * @param \Iterator $iterator
	 *
	 * @return bool
	 */
	public function accept($current, $key, $iterator) {
		if (!isset($current['enabled'])) {
			return true;
		}
		if (is_callable($current['enabled'])) {
			return $current['enabled']();
		}
		return $current['enabled'];
	}

	/**
	 * Allows retrieval of an offset by its database column, instead of its
	 * property name.
	 *
	 * @param  string $column
	 * @return null|array
	 */
    public function getOffsetByColumn(string $column): ?array
    {
//        if (!array_key_exists($column, $this->columnToPropertyMap)) {
//            return null;
//        }
//        return $this->getOffset($this->columnToPropertyMap[$column]);
        foreach ($this->getStack() as $property => $mapping) {
            if ($mapping['column'] ?? '' === $column) {
                return $mapping;
            }
        }
        return null;
    }


    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value)
    {
        return $this->setOffset($offset, $value);
    }


    /**
     * Stores the column and property in a map to avoid O(N) by column lookup
     *
     * Note that 'joinsVia' mappings do not have a local column
     *
     * @see Mapper::joinsVia()
     * @see getOffsetByColumn()
     * @inheritDoc
     */
    public function setOffset($index, $value)
    {
//        if (array_key_exists('column', $value)) {
//            $key = array_key_exists('join', $value) ? "{$value['join']}.{$value['column']}" : $value['column'];
//            $this->columnToPropertyMap[$key] = $index;
//        }
        return parent::setOffset($index, $value);
    }


    /**
     * Not supported. This collection must be keyed by model property
     *
     * @param $element
     * @return $this
     * @throws BadMethodCallException
     */
    public function add($element): self
    {
        throw new BadMethodCallException('This collection must be keyed');
    }


    /**
     * @param $element
     * @return Collection|void
     * @throws BadMethodCallException
     */
    public function prepend($element)
    {
        throw new BadMethodCallException('This collection must be keyed');
    }
}
