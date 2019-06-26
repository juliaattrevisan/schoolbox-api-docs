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

    protected $setMethods = [];

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
     */
    public function remove(string $hash)
    {
        if (!\count($this->setMethods)) {
            throw new Mapper\Exception('setMethods cannot be empty...');
        }

        $records = $this->mapper->findIdsByColumn($this->hashColumn, $hash, ['deleted' => true]);

        foreach ($records as $record) {
            $obj = $this->mapper->findByIdWithFallback($record);
            foreach ($this->setMethods as $method) {
                $obj->{$method}(null);
            }
            $this->mapper->save($obj);
        }
    }
}
