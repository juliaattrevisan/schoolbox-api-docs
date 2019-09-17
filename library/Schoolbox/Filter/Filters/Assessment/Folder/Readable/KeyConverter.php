<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\Readable;

use Schoolbox\Collection;
use Schoolbox\Filter\Filters\Assessment\Folder\KeyConverter as BaseKeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Key converter for the readable folder filter.
 *
 * Differs from the standard folder key converter only in that if no selections
 * are made, that counts as NULL (letting everything through) instead of empty
 * (letting nothing through).
 */
class KeyConverter extends BaseKeyConverter
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
}
