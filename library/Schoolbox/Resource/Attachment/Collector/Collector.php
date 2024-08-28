<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Collector;

use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Resource\Storage\IStorage;

/**
 * Collector for various types of storage handlers.
 *
 * This class' purpose is to provide a unified interface for collecting
 * `IStorage` objects which reference an upload to an attachzone.
 */
class Collector
{
    /**
     * Types of resources which this collector may collect.
     */
    public const TYPE_STORAGE = 0x1;
    public const TYPE_SBFS    = 0x2;
    public const TYPE_MYFILE  = 0x4;
    public const TYPE_WEBDAV  = 0x8;
    public const TYPE_OFFICE  = 0x10;
    public const TYPE_ALL     = 0xFF;


    /**
     * Format for the key of a storage object within the list of sources.
     *
     * The "id" pattern is the actual ID of the object: the "basename" pattern
     * is an optional filename override for the object.
     *
     * If the key doesn't match this format, assume the entire key is the ID and
     * there is no basename.
     */
    public const KEY_FORMAT = '/^(?P<id>.*?):(?P<basename>.*?)$/';


    /**
     * The list of attachments that this collector is collecting, passed from an
     * attachzone.
     *
     * key:   one of the `self::TYPE_*` constants
     * value: a list of keys, in the format `self::KEY_FORMAT`
     *
     * @var array&string[][]
     */
    protected $source;


    /**
     * The handlers which may accept a storage object.
     *
     * @var array&TypeHandlerInterface[] key: one of the `self::TYPE_*` constants
     */
    protected $handlers;


    /**
     * Constructor
     *
     * @param array&string[][]             $source
     * @param array&TypeHandlerInterface[] $handlers
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
        foreach ($this->source as $type => $keys) {
            if (!$type & $filter) {
                continue;
            }
            foreach ($keys as $key) {
                if ($result = $this->get($type, $key)) {
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

        foreach ($this->source as $type => $keys) {
            if (!$type & $filter) {
                continue;
            }
            foreach ($keys as $key) {
                if ($r = $this->get($type, $key)) {
                    $result[] = $r;
                }
            }
        }

        return $result;
    }


    /**
     * Retrieves a single result from the given handler and ID.
     *
     * Note that this method returns clones of what each handler fetches:
     * this is because if the key contains a basename then the storage object's
     * basename will be changed; this is currently a mutation on the original
     * object.
     *
     * @param int    $type
     * @param string $key
     * @return IStorage|null
     */
    protected function get($type, $key): ?IStorage
    {
        if (empty($this->handlers[$type])) {
            return null;
        }

        [$id, $replacementBasename] = $this->parseKey($key);
        $storage = clone $this->handlers[$type]->fetch($id);
        if (isset($replacementBasename)) {
            $storage->setFilename(pathinfo($replacementBasename, PATHINFO_FILENAME));
            $replacementExtension = pathinfo($replacementBasename, PATHINFO_EXTENSION);
            // override extension only when necessary
            if (!$storage->getExtension() && !empty($replacementExtension)) {
                $storage->setExtension($replacementExtension);
            }
        }
        return $storage;
    }


    /**
     * Parse an key into its ID and an optional basename (filename+extension).
     *
     * @param string $key
     * @return array 0: the ID of this file
     *               1: a replacement basename, if it exists
     */
    private function parseKey(string $key): array
    {
        if (preg_match(self::KEY_FORMAT, $key, $matches)) {
            return [$matches['id'], $matches['basename']];
        }

        // This didn't match?
        // Assume the key is the ID and there's no basename
        return [$key, null];
    }
}
