<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral\Action;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter\Page\Model;
use Schoolbox\IConfigLoader;

/**
 * Definition for the action filter on pastoral records.
 */
class Definition extends AbstractDefinition
{
    protected const NAME = 'Action Required';

    public const KEY_ACTION_REQUIRED = 'required';
    public const KEY_ACTION_NOT_REQUIRED = 'notRequired';

    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [
            self::KEY_ACTION_REQUIRED,
            self::KEY_ACTION_NOT_REQUIRED,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return $configLoader->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)
            ? [Model::PAGE_CLASS_PASTORAL]
            : [];
    }

    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        return match ($key) {
            self::KEY_ACTION_REQUIRED      => 'Action Required',
            self::KEY_ACTION_NOT_REQUIRED  => 'No Action Required',
            default                        => '',
        };
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Pastoral/Action/templates';
    }
}
