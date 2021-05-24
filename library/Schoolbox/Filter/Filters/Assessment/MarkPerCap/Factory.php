<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\MarkPerCap;

use Schoolbox\Filter\AbstractFactory;
use Schoolbox\Filter;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Selection;
use Schoolbox\L10n\RosettaStone;

class Factory extends AbstractFactory
{

    /** @var RosettaStone */
    private $rosettaStone;


    /**
     * @param Page\Option\Mapper          $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper            $selectionMapper
     * @param RosettaStone                $rosettaStone
     */
    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        RosettaStone $rosettaStone
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->rosettaStone = $rosettaStone;
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
            $this->rosettaStone
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter($filter);
    }
}
