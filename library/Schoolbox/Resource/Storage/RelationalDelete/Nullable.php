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
    protected $hashColumn = 'hash';

    /**
     * @var array
     */
    protected $setMethods = [];

    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected $hashPrefix = '';

    /**
     * Base constructor.
     *
     * @param Mapper          $mapper
     * @param LoggerInterface $logger
     */
    public function __construct(Mapper $mapper, LoggerInterface $logger)
    {
        $this->mapper = $mapper;

        $this->logger = $logger;
    }

    /**
     * @param string $hash
     * @param bool   $performDryRun
     *
     * @throws Mapper\Exception
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        if (!\count($this->setMethods)) {
            throw new Mapper\Exception('setMethods cannot be empty...');
        }

        $records = $this->mapper->findIdsByColumn($this->hashColumn, $this->hashPrefix . $hash, ['deleted' => true]);

        if (\count($records) > 0) {
            if ($performDryRun) {
                $this->logger->notice('Will unlink [' . \count($records) . '] record(s) in: ' . $this->mapper->getTable());
            } else {
                foreach ($records as $record) {
                    $obj = $this->mapper->findByIdWithFallback($record);
                    foreach ($this->setMethods as $method) {
                        $obj->{$method}(null);
                    }
                    $this->mapper->save($obj);
                }
                $this->logger->notice('[' . \count($records) . '] record(s) unlinked in: ' . $this->mapper->getTable(), ['char' => '-']);
            }
        }
    }
}
