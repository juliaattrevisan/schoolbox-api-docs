<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Component\Eportfolio;

use Psr\Log\LoggerInterface;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\My\File as MyFile;
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
     * Need to first delete any `eportfolio_component_filelist_item` that reference the `myFile` row
     *
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        /** @var GenericCollection|MyFile\Model[] $myFiles */
        $myFiles = $this->mapper->findByColumn($this->hashColumn, $hash, ['deleted' => true])->indexBy('id');

        /** @var GenericCollection|FileList[] $fileLists */
        $fileLists = $this->fileListMapper->findByColumn('file_id', $myFiles->getPropertyValues('id'), ['deleted' => true]);

        if ($myFiles->count() > 0) {
            if ($performDryRun) {
                $this->logger->notice("Will delete [{$fileLists->count()}] record(s) in: {$this->fileListMapper->getTable()}", ['char' => '-']);
                $this->logger->notice("Will delete [{$myFiles->count()}] record(s) in: {$this->mapper->getTable()}", ['char' => '-']);
            } else {
                /** @var Collection|GenericCollection[]|FileList[][] $fileListsGroupedByMyFileId */
                $fileListsGroupedByMyFileId = $fileLists->groupByCallback(static function (FileList $fileList) {
                    return $fileList->getFile()->getId();
                });
                foreach ($myFiles as $myFile) {
                    $fileListsForMyFile = $fileListsGroupedByMyFileId->offsetGet($myFile->getId());
                    try {
                        if ($fileListsForMyFile !== null) {
                            $this->fileListMapper->hardDelete($fileListsForMyFile);
                        }
                        $this->mapper->hardDelete($myFile);
                    } catch (\Exception $exception) {
                        throw new Exception($exception->getMessage());
                    }
                }
                $this->logger->notice("[{$myFiles->count()}] record(s) deleted in: {$this->mapper->getTable()}", ['char' => '-']);
            }
        }
    }
}
