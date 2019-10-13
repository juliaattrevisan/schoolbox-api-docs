<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event\Campus;

use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Selection;

/**
 * A factory for building campus filter objects.
 */
class Factory extends Filter\AbstractFactory
{
    /**
     * @var CampusMapper
     */
    private $campusMapper;


    /**
     * Constructor
     *
     * @param Page\Option\Mapper          $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper            $selectionMapper
     * @param CampusMapper                $campusMapper
     */
    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        CampusMapper $campusMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->campusMapper = $campusMapper;
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Definition|Filter\IDefinition
     */
    public function buildDefinition(Filter\Model $filter): Filter\IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper(),
            $this->campusMapper->findAll()->indexBy('id')
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter(
            $filter,
            (new Collection($this->campusMapper->findAllIds()))
                ->indexByCallback(static function ($folderId) {
                    return $folderId;
                })
        );
    }
}
