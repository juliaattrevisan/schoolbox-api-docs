<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\CurriculumNode;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter\Page;

/**
 * Definition for the badge curriculum node filter.
 */
class Definition extends AbstractDefinition
{
    /**
     * Key for finding badges with no curriculum code.
     */
    public const KEY_NONE = 'none';


    /**
     * @inheritdoc
     */
    protected const NAME = 'Curriculum Code';


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [self::KEY_NONE];
    }


    /**
     * @inheritdoc
     */
    public static function getPages(): array
    {
        return [Page\Model::PAGE_BADGE_LIST,];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        return '';
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Badge/CurriculumNode/templates';
    }
}
