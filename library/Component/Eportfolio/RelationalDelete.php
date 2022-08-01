<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Component\Eportfolio;

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage\RelationalDelete\Deletable;

class RelationalDelete extends Deletable
{
    private FileListMapper $fileListMapper;

    public function __construct(Mapper $mapper, LoggerInterface $logger, FileListMapper $fileListMapper, string $hashColumn = 'hash')
    {
        parent::__construct($mapper, $logger, $hashColumn);
        $this->fileListMapper = $fileListMapper;
    }

    /**
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        $records = $this->mapper->findByColumn($this->hashColumn, $hash, ['deleted' => true]);

        if ($records->count() > 0) {
            if ($performDryRun) {
                $this->logger->notice('Will delete [' . $records->count() . '] record(s) in: ' . $this->fileListMapper->getTable(), ['char' => '-']);
            } else {
                foreach ($records as $record) {
                    $file = $this->fileListMapper->findByColumn('file_id', $record->getId(), ['deleted' => true]);
                    try {
                        $this->fileListMapper->hardDelete($file);
                    } catch (\Exception $exception) {
                        throw new Exception($exception->getMessage());
                    }
                }
                $this->logger->notice('[' . $records->count() . '] record(s) deleted in: ' . $this->mapper->getTable(), ['char' => '-']);
            }
        }
    }
}
