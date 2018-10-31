<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\GenericCollection;
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
        return false;
//        return true; // TODO Turned off as not-yet implemented in front-end so no need to include in query
    }


    /**
     * @inheritdoc
     */
    public function getAudiencingSql(User $user, string $searchPlaceholder = null): string
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

    /**
     * @inheritdoc
     */
    public function getSearchSql(User $user): string
    {
        return '';
    }


    /**
     * @inheritdoc
     * @param GenericCollection|User[] $users
     */
    public function getValues(GenericCollection $users): array
    {
        $values = [];
        foreach ($this->getUserAccessMapper()->findByColumn('uid', $users->getPropertyValues('id')) as $access) {
            $values[] = "{$access->getService()}.{$access->getServiceIdentifier()}.{$access->getAccessLevel()}";
        }
        return $values;
    }
}
