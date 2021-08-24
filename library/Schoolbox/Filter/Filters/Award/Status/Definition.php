<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Award\Status;

use Schoolbox\Filter;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Badge\Award\Model as Award;

/**
 * Award status - Goal and Achievement
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    protected const NAME = 'Status';

    /**
     * @return string[]
     */
    public static function getStaticCustomKeys(): array
    {
        return [
            Award::AWARD_STATUS_GOAL,
            Award::AWARD_STATUS_ACHIEVEMENT,
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return self::getStaticCustomKeys();
    }

    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Filter\Page\Model::PAGE_AWARD_LIST,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        return ucfirst($key);
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Award/Status/templates';
    }
}
