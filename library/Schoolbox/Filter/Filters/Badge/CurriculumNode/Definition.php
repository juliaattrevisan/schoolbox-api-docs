<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\CurriculumNode;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter\Page;
use Schoolbox\IConfigLoader;

/**
 * Definition for the badge curriculum node filter.
 *
 * Curriculum nodes will be displayed in a selectize, as the list of nodes is
 * expected to be too large to load in a single dropdown.
 */
class Definition extends AbstractDefinition
{
    /**
     * Key for finding badges with no curriculum code.
     */
    public const KEY_NONE = 'none';


    /**
     * Key for finding badges with any curriculum code.
     */
    public const KEY_ANY = 'any';


    /**
     * Key for finding any badge at all, whether or not it has a code.
     *
     * Required so that the curriculum code selector is even configurable.
     */
    public const KEY_ALL = 'all';


    /**
     * @inheritdoc
     */
    protected const NAME = 'Curriculum Code';


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [self::KEY_ALL];
    }


    /**
     * This definition is available on the badge list page, but only if
     * curriculum tagging is enabled.
     *
     * @param IConfigLoader $configLoader
     * @return array
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return $configLoader->get('curriculum_tagging_enabled')
            ? [Page\Model::PAGE_BADGE_LIST,]
            : [];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if ($key === self::KEY_NONE) {
            return 'Badges with no curriculum code';
        }
        if ($key === self::KEY_ANY) {
            return 'Badges with at least one curriculum code';
        }
        if ($key === self::KEY_ALL) {
            return 'All badges';
        }
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
