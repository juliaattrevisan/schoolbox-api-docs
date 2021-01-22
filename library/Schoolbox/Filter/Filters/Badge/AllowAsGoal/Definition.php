<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\AllowAsGoal;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\IConfigLoader;

/**
 * Hidden filter to filter badges to those that are allowed to be goals
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    protected const NAME = 'AllowAsGoal';

    public const KEY_ALLOWED     = '1';
    public const KEY_NOT_ALLOWED = '0';


    /** @var Collection|string[] */
    private $allowAsGoal;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param Collection                         $allowAsGoal
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        Collection $allowAsGoal
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->allowAsGoal = $allowAsGoal->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return $this->allowAsGoal->getAll();
    }


    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Filter\Page\Model::PAGE_BADGE_ALLOW_AS_GOAL,
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
        throw new InvalidArgumentException("Unknown AllowAsGoal key: $key");
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Badge/AllowAsGoal/templates';
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
