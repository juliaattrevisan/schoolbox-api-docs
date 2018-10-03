<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\User;

interface IAudience
{

    /**
     * Returns this implementation's entityType
     *
     * @return string
     */
    public function getEntityType(): string;


    /**
     * Returns whether the specified user can audience to this entity type
     *
     * @param User $user
     * @return bool
     */
    public function canAudience(User $user): bool;


    /**
     * Returns whether the specified user can audience to a value/modifier of this entity type
     *
     * @param User        $user
     * @param null|string $entityValue
     * @param string      $modifier
     * @return bool
     */
    public function canAudienceToValueAndModifier(User $user, ?string $entityValue, string $modifier = Model::RELATIONSHIP_SELF): bool;
}
