<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Folder;

use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\Filter\IKeyConverter;
use Schoolbox\Filter\IRunTimeOptions;
use Schoolbox\User;
use Schoolbox\Mapper;


/**
 * A factory for building FolderFilters
 * @todo
 */
class Factory extends AbstractFilterFactory
{

    /**
     * Build a filter definition, ready to use in the admin pages
     *
     * No dynamic options or user-selections relevant.
     *
     * @param Filter\Model $filter
     * @return IFilterDefinition
     */
    public function buildDefinition(Filter\Model $filter)
    {
        // TODO: Implement buildDefinition() method.
    }


    /**
     * @inheritdoc
     * @throws Mapper\Exception
     */
    public function buildComponent(User $user, $page, IRunTimeOptions $runTimeOptions = null)
    {
        $filter          = $this->getFilterMapper()->findForClass(Definition::class);
        $definition      = $this->buildDefinition($filter);
        $savedSelections = $this->getSavedSelections($user, $page);

        return new Component($definition, $user, $page, $savedSelections, $this->getFilterSelectionMapper());
    }


    /**
     * Returns a key converter, able to convert between concrete keys and the entities they represent
     *
     * @return IKeyConverter
     */
    public function buildKeyConverter()
    {
        // TODO: Implement buildKeyConverter() method.
    }
}
