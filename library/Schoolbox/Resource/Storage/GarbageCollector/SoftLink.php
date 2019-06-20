<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GarbageCollector;

use Schoolbox\Mapper;

class SoftLink implements IGarbageCollector
{

    protected $mapper;

    protected $hashColumn = 'hash';

    protected $setMethod;

    /**
     * Base constructor.
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * @param string $hash
     *
     * @return mixed|void
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     */
    public function remove(string $hash)
    {
        $records = $this->mapper->findIdsByColumn($this->hashColumn, $hash);

        foreach ($records as $record) {
            $obj = $this->mapper->findById($record);
            $obj->{$this->setMethod}(null);
            $this->mapper->save($obj);
        }
    }
}
