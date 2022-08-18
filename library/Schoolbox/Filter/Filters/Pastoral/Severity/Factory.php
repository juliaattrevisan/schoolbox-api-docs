<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral\Severity;

use Schoolbox\Filter\AbstractFactory;
use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\IKeyConverter;
use Schoolbox\Filter\Model;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Selection;
use Schoolbox\Pastoral;

/**
 * Factory for pastoral severity filter.
 */
class Factory extends AbstractFactory
{
    /**
     * Constructor
     *
     * @param Page\Option\Mapper          $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper            $selectionMapper
     * @param Pastoral\Severity\Mapper    $pastoralSeverityMapper
     */
    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        private Pastoral\Severity\Mapper $pastoralSeverityMapper,
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
    }


    /**
     * @inheritdoc
     */
    public function buildDefinition(Model $filter): IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper(),
            $this->pastoralSeverityMapper->findAll(),
        );
    }

    /**
     * @inheritdoc
     */
    public function buildKeyConverter(Model $filter): IKeyConverter
    {
        return new KeyConverter($filter, $this->pastoralSeverityMapper->findAllIds());
    }
}
