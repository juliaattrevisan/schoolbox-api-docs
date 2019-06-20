<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GarbageCollector;

use Schoolbox\Mapper;

class HardLink implements IGarbageCollector
{
    protected $mapper;

    protected $hashColumn = 'hash';

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
        $records = $this->mapper->findByColumn($this->hashColumn, $hash, ['deleted' => true]);

        if ($records->count() > 0) {
            foreach ($records as $record) {
                $this->mapper->hardDelete($record);
            }
        }
    }
}
