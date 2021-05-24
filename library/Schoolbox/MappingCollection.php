<?php

/**
 * Collection intended for column->property mappings for a mapper class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox;

use function array_key_exists;

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
    private $columnToPropertyMap = [];

    /**
     * Retrieves an iterator which filters out any element in the collection
     * which doesn't pass $this->accept().
     *
     * @return \Traversable
     */
    public function getIterator()
    {
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
    public function accept($current, $key, $iterator)
    {
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
        if (!array_key_exists($column, $this->columnToPropertyMap)) {
            return null;
        }
        return $this->getOffset($this->columnToPropertyMap[$column]);
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
        if (array_key_exists('column', $value)) {
            // If the column exists on this table, we only need the column name.
            // If the column exists on a different table, we need to prefix with the join name
            // This ensures we don't clobber the array item
            $key = $value['column'];
            if (array_key_exists('join', $value) && ($value['join_def']['reverse'] ?? false)) {
                $key = "{$value['join']}.$key";
            }
        } elseif (array_key_exists('join_def', $value)) {
            // This is a joinsVia()
            // Very unlikely we'd ever attempt to reverse lookup, but still store it for completion.
            // eg User model campus collection property => "userCampus.campus"
            $key = "{$value['join']}.{$value['join_def']['multi']['other_key']}";
        } else {
            // This is the hasMap() call of a hasMap() + hasJoin()[multi] call pair
            // Don't bother supporting this.
            $key = null;
        }

        if ($key) {
            $this->columnToPropertyMap[$key] = $index;
        }

        return parent::setOffset($index, $value);
    }


    /**
     * Not supported. This collection must be keyed by model property
     *
     * @param mixed $element
     * @return $this
     * @throws BadMethodCallException
     */
    public function add($element): self
    {
        throw new BadMethodCallException('This collection must be keyed');
    }


    /**
     * @param mixed $element
     * @return Collection|void
     * @throws BadMethodCallException
     */
    public function prepend($element)
    {
        throw new BadMethodCallException('This collection must be keyed');
    }
}
