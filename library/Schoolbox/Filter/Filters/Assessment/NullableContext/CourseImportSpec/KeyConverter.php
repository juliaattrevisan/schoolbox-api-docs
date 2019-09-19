<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\CourseImportSpec;

use Schoolbox\Collection;
use Schoolbox\Filter\Filters\Assessment\NullableContext\KeyConverter as BaseKeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Key converter for the course import spec filter.
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
