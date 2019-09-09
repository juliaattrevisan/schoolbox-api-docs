<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\FolderYearLevel;

use InvalidArgumentException;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\YearLevel;
use UnexpectedValueException;
use function array_merge;

class Definition extends Filter\AbstractDefinition
{
    public const KEY_NONE = 'none'; // assessment belongs to folder that has no year-level association

    // The name to expose in the UI
    protected const NAME = 'Year Level';

    /** @var GenericCollection|YearLevel\Model[] */
    private $yearLevels;


    /**
     * @param Filter\Model                        $filter
     * @param Filter\Page\Option\Mapper           $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper  $filterDefaultMapper
     * @param GenericCollection|YearLevel\Model[] $yearLevels
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        GenericCollection $yearLevels
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->yearLevels = $yearLevels->shallowClone()->indexByCallback(static function (YearLevel\Model $yearLevel) {
            return $yearLevel->getId();
        });
    }


    /**
     * @inheritdoc
     */
    public static function getPages(): array
    {
        return [
            Filter\Page\Model::PAGE_ASSESSMENT_CALENDAR,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if ($key === self::KEY_NONE) {
            return 'None';
        }
        if ($this->yearLevels[$key]) {
            return $this->yearLevels[$key]->getName();
        }
        throw new InvalidArgumentException("Unknown folder YearLevel key: $key");
    }

    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [self::KEY_NONE];
    }

    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getAbstractKeys(): array
    {

        // Ensure numeric ids are cast to string keys
        $keys = array_merge($this->getCustomKeys(), array_map(static function (int $yearLevelId) {
            return (string)$yearLevelId;
        }, $this->yearLevels->keys()));

        return $keys;
    }

    /**
     * @inheritdoc
     * @throws UnexpectedValueException
     */
    protected function insertDynamicOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $options = $pageFilter->getOptions();

        // Ensure it contains all YearLevels
        foreach ($this->yearLevels as $yearLevel) {
            $abstractKey = (string)$yearLevel->getId();
            if (!$options->offsetExists($abstractKey)) {
                $option = (new Filter\Page\Option\Model())
                    ->setFilterPage($pageFilter)
                    ->setKey($abstractKey)
                    ->setAvailable($this->getInitialAvailability($abstractKey, $pageFilter))
                    ->setDefaults(new GenericCollection(Filter\Page\Option\Defaults\Model::class));
                $options->setOffset($abstractKey, $option);
            }
        }
        return $options;
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/FolderYearLevel/templates';
    }
}
