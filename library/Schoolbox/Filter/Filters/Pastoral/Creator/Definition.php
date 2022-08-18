<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral\Creator;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter\Page\Model;
use Schoolbox\IConfigLoader;

/**
 * Definition for the creator filter on pastoral records.
 */
class Definition extends AbstractDefinition
{
    protected const NAME = 'Creator';

    public const KEY_CURRENT_USER = 'currentUser';
    public const KEY_OTHER_USERS = 'otherUsers';

    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [
            self::KEY_CURRENT_USER,
            self::KEY_OTHER_USERS,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Model::PAGE_CLASS_PASTORAL,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        return match ($key) {
            self::KEY_CURRENT_USER => 'Current User',
            self::KEY_OTHER_USERS  => 'Other Users',
            default                => '',
        };
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Pastoral/Creator/templates';
    }
}
