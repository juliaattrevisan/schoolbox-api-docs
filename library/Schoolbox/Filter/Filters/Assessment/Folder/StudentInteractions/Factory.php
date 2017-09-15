<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder\StudentInteractions;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\User;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;


/**
 * A factory for building 'StudentFolder' filter objects
 */
class Factory extends FolderFilter\Factory
{

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
            $this->getDefaultsMapper()
        );
    }


    /**
     * @inheritdoc
     * @throws DefinitionException If join not defined
     */
    protected function buildExtraComponentArgs(
        User $user,
        Filter\Page\Model $pageFilter,
        Filter\IDefinition $definition,
        Collection $overrides,
        User $viewee = null
    ) {
        $options = $pageFilter->getOptions();
        $folders = $this->getFolderMapper()->createCollection();

        if($options[Definition::KEY_CLASSES]->getAvailable()){
            // TODO insert enrolled classes
        }
        if($options[Definition::KEY_GROUPS]->getAvailable()){
            // TODO insert enrolled groups
        }
        if($options[Definition::KEY_RESPONSE]->getAvailable()){
            $folders = $folders->mergeUnique($this->getFolderMapper()->findFoldersWithResponses($viewee), 'id');
        }
        if($options[Definition::KEY_FEEDBACK]->getAvailable()){
            $folders = $folders->mergeUnique($this->getFolderMapper()->findFoldersWithFeedbackToStudent($viewee), 'id');
        }
        $folders->sortBy('name', 'ASC');

        return [
            'folders' => $folders,
        ];
    }
}
