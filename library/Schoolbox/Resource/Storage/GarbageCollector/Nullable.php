<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GarbageCollector;

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper;

class Nullable implements IGarbageCollector
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
     *
     * @param bool   $performDryRun
     *
     * @return mixed|void
     * @throws Mapper\Exception
     */
    public function remove(string $hash, bool $performDryRun)
    {
        if (!\count($this->setMethods)) {
            throw new Mapper\Exception('setMethods cannot be empty...');
        }

        $records = $this->mapper->findIdsByColumn($this->hashColumn, $hash, ['deleted' => true]);

        foreach ($records as $record) {
            if ($performDryRun) {
                $this->logger->notice('Will unlink ['. \count($records) .'] record(s) in: ' . $this->mapper->getTable(), ['char' => '+']);
            } else {
                $obj = $this->mapper->findByIdWithFallback($record);
                foreach ($this->setMethods as $method) {
                    $obj->{$method}(null);
                }
                $this->mapper->save($obj);
                $this->logger->notice('['. \count($records) . '] record(s) unlinked in: ' . $this->mapper->getTable(), ['char' => '-']);
            }
        }
    }
}
