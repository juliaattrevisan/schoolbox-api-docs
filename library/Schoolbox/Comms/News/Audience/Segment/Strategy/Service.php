<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Segment\Strategy\SimpleStrategy;
use Schoolbox\User;

class Service extends SimpleStrategy
{

    /**
     * @return string
     */
    public static function getEntityType(): string
    {
        return 'service';
    }

    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // A user may have no service permissions
        return true;
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
