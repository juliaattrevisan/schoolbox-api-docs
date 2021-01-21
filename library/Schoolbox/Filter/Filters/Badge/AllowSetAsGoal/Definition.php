<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\AllowSetAsGoal;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\IConfigLoader;

/**
 * Hidden filter to filter badges by allow set as goal
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    protected const NAME = 'AllowSetAsGoal';

    const KEY_ALLOWED     = '1';
    const KEY_NOT_ALLOWED = '0';


    /** @var Collection|string[] */
    private $allowSetAsGoal;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param Collection                         $allowSetAsGoal
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        Collection $allowSetAsGoal
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->allowSetAsGoal = $allowSetAsGoal->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return $this->allowSetAsGoal->getAll();
    }


    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Filter\Page\Model::PAGE_BADGE_SET_AS_GOAL,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if (self::KEY_ALLOWED === $key) {
            return 'Allowed';
        }
        if (self::KEY_NOT_ALLOWED === $key) {
            return 'Not allowed';
        }
        throw new InvalidArgumentException("Unknown AllowSetAsGoal key: $key");
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Badge/AllowSetAsGoal/templates';
    }


    /**
     * @inheritdoc
     */
    public static function getEnabledInitialState(string $page): bool
    {
        return true;
    }


    /**
     * @inheritdoc
     */
    public static function getUserConfigurableInitialState(string $page): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function getInitialSelectedState(string $key, string $page, string $roleType): bool
    {
        return in_array($key, [self::KEY_ALLOWED], true);
    }
}
