<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\RelationalDelete;

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper;

class Deletable implements IRelationalDelete
{
    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * @var string
     */
    protected $hashColumn;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Base constructor.
     *
     * @param Mapper          $mapper
     * @param LoggerInterface $logger
     * @param string          $hashColumn
     */
    public function __construct(Mapper $mapper, LoggerInterface $logger, string $hashColumn = 'hash')
    {
        $this->mapper       = $mapper;
        $this->logger       = $logger;
        $this->hashColumn   = $hashColumn;
    }

    /**
     * @param string $hash
     * @param bool   $performDryRun
     *
     * @throws Mapper\Exception
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        $records = $this->mapper->findByColumn($this->hashColumn, $hash, ['deleted' => true]);

        if ($records->count() > 0) {
            if ($performDryRun) {
                $this->logger->notice('Will delete [' . $records->count() . '] record(s) in: ' . $this->mapper->getTable(), ['char' => '-']);
            } else {
                foreach ($records as $record) {
                    try {
                        $this->mapper->hardDelete($record);
                    } catch (\Exception $exception) {
                        throw new Mapper\Exception($exception->getMessage());
                    }
                }
                $this->logger->notice('[' . $records->count() . '] record(s) deleted in: ' . $this->mapper->getTable(), ['char' => '-']);
            }
        }
    }
}
