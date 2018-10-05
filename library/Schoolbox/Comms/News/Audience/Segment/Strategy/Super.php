<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\User;

class Super extends SimpleStrategy
{
    public const ENTITY_VALUE = '1';
    public static function getEntityType(): string
    {
        return 'super';
    }

    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // Only valid entityValue is TRUE (TODO update comment to reference where this is defined)
        return false;
    }


    /**
     * @inheritdoc
     */
    public function getSearchSql(User $user, string $searchPlaceholder = null): string
    {
        return "SELECT 0, 'name todo', 'type todo' from dual WHERE false"; // TODO
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        return ''; // TODO
    }
}
