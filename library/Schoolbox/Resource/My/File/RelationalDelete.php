<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\My\File;

use Component\Eportfolio\FileList;
use Component\Eportfolio\FileListMapper;
use Psr\Log\LoggerInterface;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\My\File as MyFile;
use Schoolbox\Resource\Storage\RelationalDelete\Deletable;

class RelationalDelete extends Deletable
{
    private FileListMapper $fileListItemMapper;

    public function __construct(Mapper $mapper, LoggerInterface $logger, FileListMapper $fileListMapper)
    {
        parent::__construct($mapper, $logger);
        $this->fileListItemMapper = $fileListMapper;
    }

    /**
     * Need to first delete any `eportfolio_component_filelist_item` that reference the `myFile` row
     *
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        /** @var GenericCollection|MyFile\Model[] $myFilesIndexedById */
        $myFilesIndexedById = $this->mapper->findByColumn($this->hashColumn, $hash, ['deleted' => true])->indexBy('id');

        /** @var GenericCollection|FileList[] $fileListItems */
        $fileListItems = $this->fileListItemMapper->findByColumn('file_id', $myFilesIndexedById->keys(), ['deleted' => true]);

        if ($myFilesIndexedById->count() > 0) {
            if ($performDryRun) {
                if ($fileListItems->count()) {
                    $this->logger->notice("Will delete [{$fileListItems->count()}] record(s) in: {$this->fileListItemMapper->getTable()}", ['char' => '-']);
                }
                $this->logger->notice("Will delete [{$myFilesIndexedById->count()}] record(s) in: {$this->mapper->getTable()}", ['char' => '-']);
            } else {
                /** @var Collection|GenericCollection[]|FileList[][] $fileListsGroupedByMyFileId */
                $fileListsGroupedByMyFileId = $fileListItems->groupByCallback(static function (FileList $fileList) {
                    return $fileList->getFile()->getId();
                });
                foreach ($myFilesIndexedById as $myFile) {
                    $fileListsForMyFile = $fileListsGroupedByMyFileId->offsetGet($myFile->getId());
                    try {
                        if ($fileListsForMyFile !== null) {
                            $this->fileListItemMapper->hardDelete($fileListsForMyFile);
                        }
                        $this->mapper->hardDelete($myFile);
                    } catch (\Exception $exception) {
                        throw new Exception($exception->getMessage());
                    }
                }
                if ($fileListItems->count()) {
                    $this->logger->notice("[{$fileListItems->count()}] record(s) deleted in: {$this->fileListItemMapper->getTable()}", ['char' => '-']);
                }
                $this->logger->notice("[{$myFilesIndexedById->count()}] record(s) deleted in: {$this->mapper->getTable()}", ['char' => '-']);
            }
        }
    }
}
