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
    public function remove(string $hash)
    {
        $fileListItemMapper = new FileListMapper();

        $records = $this->mapper->findByColumn($this->hashColumn, $hash, ['deleted' => true]);

        if ($records->count() > 0) {
            foreach ($records as $record) {
                $file = $fileListItemMapper->findByColumn('file_id', $record->getId(), ['deleted' => true]);
                $fileListItemMapper->hardDelete($file);
            }
        }
    }
}
