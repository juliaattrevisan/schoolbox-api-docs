<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Collector;


use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Resource\Storage\IStorage;

class Collector
{

    const TYPE_STORAGE = 0x1;
    const TYPE_SBFS    = 0x2;
    const TYPE_MYFILE  = 0x4;
    const TYPE_WEBDAV  = 0x8;
    const TYPE_ALL     = 0xF;

    /** @var array */
    protected $source;

    /** @var TypeHandlerInterface[] */
    protected $handlers;


    /**
     * @param array $source - in the form $source[$type][$i] = $id
     * @param TypeHandlerInterface[] $handlers
     */
    public function __construct($source, $handlers)
    {
        $this->source = $source;
        $this->handlers = $handlers;
    }


    /**
     * @param int $filter
     * @throws ResourceException
     * @return IStorage
     */
    public function getFirst($filter = self::TYPE_ALL)
    {
        foreach ($this->source as $type => $ids) {
            if (!$type & $filter) {
                continue;
            }
            foreach ($ids as $id) {
                if($result = $this->get($type, $id)) {
                    return $result;
                }
            }
        }
        throw new ResourceException("Could not find a matching resource");
    }


    /**
     * @param int $filter
     * @return IStorage[]
     */
    public function getAll($filter = self::TYPE_ALL)
    {
        $result = array();

        foreach ($this->source as $type => $ids) {
            if(!$type & $filter) {
                continue;
            }
            foreach ($ids as $id) {
                if($r = $this->get($type, $id)) {
                    $result[] = $r;
                }
            }
        }

        return $result;
    }


    /**
     * @param int $type
     * @param string $id
     * @return IStorage
     */
    protected function get($type, $id)
    {

        if (empty($this->handlers[$type])) {
            return null;
        }

        return $this->handlers[$type]->fetch($id);
    }

} 