<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\RelationalDelete;

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper;

class Nullable implements IRelationalDelete
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
     * @var array
     */
    protected $fields = [];

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $hashPrefix;

    /**
     * Base constructor.
     *
     * @param Mapper          $mapper
     * @param LoggerInterface $logger
     * @param array           $fields
     * @param string          $hashColumn
     * @param string          $hashPrefix
     */
    public function __construct(Mapper $mapper, LoggerInterface $logger, array $fields, string $hashColumn = 'hash', string $hashPrefix = '')
    {
        $this->mapper       = $mapper;
        $this->logger       = $logger;
        $this->hashColumn   = $hashColumn;
        $this->fields       = $fields;
        $this->hashPrefix   = $hashPrefix;
    }

    /**
     * @param string $hash
     * @param bool   $performDryRun
     *
     * @throws Mapper\Exception
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        if (!\count($this->fields)) {
            throw new Mapper\Exception('Fields cannot be empty...');
        }

        $records = $this->mapper->findIdsByColumn($this->hashColumn, $this->hashPrefix . $hash, ['deleted' => true]);

        if (\count($records) > 0) {
            if ($performDryRun) {
                $this->logger->notice('Will unlink [' . \count($records) . '] record(s) in: ' . $this->mapper->getTable(), ['char' => '-']);
            } else {
                foreach ($records as $record) {
                    $obj = $this->mapper->findByIdWithFallback($record);
                    foreach ($this->fields as $field) {
                        $field = 'set' . ucwords($field);
                        $obj->{$field}(null);
                    }
                    $this->mapper->save($obj);
                }
                $this->logger->notice('[' . \count($records) . '] record(s) unlinked in: ' . $this->mapper->getTable(), ['char' => '-']);
            }
        }
    }
}
