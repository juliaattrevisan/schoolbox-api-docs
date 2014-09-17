<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Collector;


use Schoolbox\Storage;

class Collector
{

    const TYPE_STORAGE = 0x1;
    const TYPE_SBFS    = 0x2;
    const TYPE_FOLIO   = 0x4;
    const TYPE_ALL     = 0x7;

    /** @var array */
    protected $source;

    /** @var TypeHandlerInterface[] */
    protected $handlers;


    /**
     * @param array $source
     * @param TypeHandlerInterface[] $handlers
     */
    public function __construct($source, $handlers)
    {
        $this->source = $source;
        $this->handlers = $handlers;
    }


    /**
     * @param int $filter
     * @return Storage
     */
    public function getFirst($filter = self::TYPE_ALL)
    {
        foreach (array_keys($this->source) as $index) {
            if ($this->detectType($index) & $filter) {
                return $this->get($index);
            }
        }

        return null;
    }


    /**
     * @param int $filter
     * @return Storage[]
     */
    public function getAll($filter = self::TYPE_ALL)
    {
        $result = array();

        foreach (array_keys($this->source) as $index) {
            if ($this->detectType($index) & $filter) {
                $result[] = $this->get($index);
            }
        }

        return $result;
    }


    /**
     * @param int $i
     * @return object
     */
    protected function get($i)
    {
        $type = $this->detectType($this->source[$i]);

        if (empty($handlers[$type])) {
            return null;
        }

        return $this->handlers[$type]->fetch($this->source[$i]);
    }


    /**
     * @param string $id
     * @return int
     */
    public function detectType($id)
    {
        foreach ($this->handlers as $type => $handler) {
            if ($handler->matches($id)) {
                return (int)$type;
            }
        }
        return 0;
    }

} 