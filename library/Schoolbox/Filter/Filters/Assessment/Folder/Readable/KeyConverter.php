<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\Readable;

use Schoolbox\Collection;
use Schoolbox\Filter\Filters\Assessment\Folder\KeyConverter as BaseFolderKeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Specialized key converter for the readable folder filter.
 *
 * It deviates from the standard filter in that if no filters are selected, then
 * that is treated as NULL instead of as an empty collection.
 *
 * (TODO figure out what the above actually means)
 */
class KeyConverter extends BaseFolderKeyConverter
{
    /**
     * @inheritDoc
     */
    public function getSelectedItems(GenericCollection $availableSelections, User $user, string $page): ?Collection
    {
        if ($availableSelections->count() === 0) {
            return null;
        }
        return parent::getSelectedItems($availableSelections, $user, $page);
    }


    /**
     * This key is valid for any user who may view this folder.
     *
     * @param string $key
     * @param User   $user
     * @param string $page
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        // TODO check for read permissions
    }


    /**
     * This key converter doesn't store a list of folder IDs, because said list
     * is dynamic based on whatever's in the selectize box.
     *
     * @param string $key
     * @param User   $user
     * @param string $page
     * @return int
     */
    public function getItem(string $key, User $user, string $page): int
    {
        $this->assertValidKey($key, $user, $page);
        return (int)$key;
    }
}
