<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\StudentInteractions;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\Folder\KeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Permission\Access\UserAccess;
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
     * @inheritDoc
     * @return Filter\IComponent|FolderFilter\Component
     * @throws DefinitionException if join $name isn't on this mapper
     * @throws \UnexpectedValueException
     * @throws InvalidArgumentException If neither $selections or $overrides are supplied
     */
    public function buildComponent(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        array $routeArgs,
        GenericCollection $overrides
    ): Filter\IComponent {
        $options = $pageFilter->getOptions();

        if (!$viewee) {
            throw new InvalidArgumentException('$viewee cannot be null for StudentInteraction filter');
        }

        $getClasses = $options[Definition::KEY_CLASSES]->getAvailable() && $viewee->getRole()->isStudent();
        $getGroups  = $options[Definition::KEY_GROUPS]->getAvailable();

        if ($getClasses || $getGroups) {
            // Note: '4' is a hack to mean 'INVALID', to force no group folders to be matched.
            $groupAccessLevel = $getGroups ? UserAccess::ACCESS_READ : UserAccess::ACCESS_FULL + 1;
            $folders          = $this->getFolderMapper()->findForUser($viewee, $getClasses, $groupAccessLevel, true)->indexBy('id');
        } else {
            $folders = $this->getFolderMapper()->createCollection();
        }

        if ($options->keyExists(Definition::KEY_RESPONSE) && $options[Definition::KEY_RESPONSE]->getAvailable()) {
            $folders = $folders->mergeUnique($this->getFolderMapper()->findFoldersWithResponses($viewee), 'id');
        }
        if ($options->keyExists(Definition::KEY_FEEDBACK) && $options[Definition::KEY_FEEDBACK]->getAvailable()) {
            $folders = $folders->mergeUnique($this->getFolderMapper()->findFoldersWithFeedbackToStudent($viewee), 'id');
        }

        return new FolderFilter\Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()],
            $this->getSelectionMapper(),
            $folders->sortBy('name', 'ASC')
        );
    }
}
