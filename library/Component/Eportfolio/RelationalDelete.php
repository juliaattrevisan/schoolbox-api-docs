<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Component\Eportfolio;

use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage\RelationalDelete\Deletable;

class RelationalDelete extends Deletable
{
    /**
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        $fileListItemMapper = new FileListMapper();

        $records = $this->mapper->findByColumn($this->hashColumn, $hash, ['deleted' => true]);

        if ($records->count() > 0) {
            if ($performDryRun) {
                $this->logger->notice('Will delete [' . $records->count() . '] record(s) in: ' . $fileListItemMapper->getTable(), ['char' => '-']);
            } else {
                foreach ($records as $record) {
                    $file = $fileListItemMapper->findByColumn('file_id', $record->getId(), ['deleted' => true]);
                    try {
                        $fileListItemMapper->hardDelete($file);
                    } catch (\Exception $exception) {
                        throw new Exception($exception->getMessage());
                    }
                }
                $this->logger->notice('[' . $records->count() . '] record(s) deleted in: ' . $this->mapper->getTable(), ['char' => '-']);
            }
        }
    }
}
