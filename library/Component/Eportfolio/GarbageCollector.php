<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Component\Eportfolio;

use Schoolbox\Resource\Storage\GarbageCollector\HardLink;
use Schoolbox\Resource\Storage\GarbageCollector\IGarbageCollector;

class GarbageCollector extends HardLink implements IGarbageCollector
{
    /**
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun)
    {
        $fileListItemMapper = new FileListMapper();

        $records = $this->mapper->findByColumn($this->hashColumn, $hash, ['deleted' => true]);

        if ($records->count() > 0) {
            if ($performDryRun) {
                $this->logger->notice('Will delete existing record(s) in: ' . $this->mapper->getTable());
            } else {
                foreach ($records as $record) {
                    $file = $fileListItemMapper->findByColumn('file_id', $record->getId(), ['deleted' => true]);
                    try {
                        $fileListItemMapper->hardDelete($file);
                    } catch (\Exception $exception) {
                        throw new Mapper\Exception($exception->getMessage());
                    }
                }
                $this->logger->notice('Record(s) deleted in: ' . $this->mapper->getTable(), ['char' => '-']);
            }
        }
    }
}
