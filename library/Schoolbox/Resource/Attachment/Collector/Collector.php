<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Collector;


use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Resource\Storage\IStorage;

class Collector
{
    public const TYPE_STORAGE = 0x1;
    public const TYPE_SBFS    = 0x2;
    public const TYPE_MYFILE  = 0x4;
    public const TYPE_WEBDAV  = 0x8;
    public const TYPE_OFFICE  = 0x10;
    public const TYPE_ALL     = 0xFF;

    /**
     * @var array
     */
    protected $source;


    /**
     * @var TypeHandlerInterface[]
     */
    protected $handlers;


    /**
     * Constructor
     *
     * @param array                  $source in the form $source[$type][$i] = $id
     * @param TypeHandlerInterface[] $handlers
     */
    public function __construct(array $source, array $handlers)
    {
        $this->source   = $source;
        $this->handlers = $handlers;
    }


    /**
     * Retrieves the first result in the collector which matches the filter.
     *
     * @param int $filter A bitmask of the `self::TYPE_*` constants
     * @throws ResourceException
     * @return IStorage
     */
    public function getFirst($filter = self::TYPE_ALL): IStorage
    {
        foreach ($this->source as $type => $ids) {
            if (!$type & $filter) {
                continue;
            }
            foreach ($ids as $id) {
                if ($result = $this->get($type, $id)) {
                    return $result;
                }
            }
        }
        throw new ResourceException("Could not find a matching resource");
    }


    /**
     * Retrieves all results in the collector which match the filter.
     *
     * @param int $filter A bitmask of the `self::TYPE_*` constants
     * @return IStorage[]
     */
    public function getAll($filter = self::TYPE_ALL): array
    {
        $result = [];

        foreach ($this->source as $type => $ids) {
            if (!$type & $filter) {
                continue;
            }
            foreach ($ids as $id) {
                if ($r = $this->get($type, $id)) {
                    $result[] = $r;
                }
            }
        }

        return $result;
    }


    /**
     * Retrieves a single result from the given handler and ID.
     *
     * @param int    $type
     * @param string $id
     * @return IStorage|null
     */
    protected function get($type, $id): ?IStorage
    {
        if (empty($this->handlers[$type])) {
            return null;
        }

        [$id, $replacementBasename] = $this->parseId($id);
        $storage = clone $this->handlers[$type]->fetch($id);
        if (isset($storage, $replacementBasename)) {
            $storage->setBasename($replacementBasename);
        }
        return $storage;
    }


    /**
     * Parse an ID into its identifier and optional basename
     * (filename+extension).
     *
     * @param string $id
     * @return array 0: the actual ID
     *               1: a replacement basename, if it exists
     */
    private function parseId(string $id): array
    {
        return array_pad(explode(':', $id, 2), 2, null);
    }
}
