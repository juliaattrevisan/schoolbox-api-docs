<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment;

/**
 * A type of audience segmentation, eg 'folder', 'role', 'user' etc
 */
interface IStrategy extends ISearch, ITranslate
{

    /**
     * Returns the entity type for this strategy
     *
     * @return string
     */
    public function getEntityType(): string;


    /**
     * Returns whether this segment can target null entities. Eg 'users who are not in any classes or groups'
     *
     * Note that null !== empty. Eg year = ['', null] are different. The first is a non-null entity.
     *
     * @return bool
     */
    public function canTargetNullEntityValue(): bool;
}
