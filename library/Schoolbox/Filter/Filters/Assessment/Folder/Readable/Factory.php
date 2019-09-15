<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\Readable;

use InvalidArgumentException;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;


/**
 * A factory for building 'Readable' filter objects.
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
            throw new InvalidArgumentException('$viewee cannot be null for Readable Folders filter');
        }

        // Folders: could be ANY and ALL groups or classes. Is this okay?
        $getMyClasses    = $options[Definition::KEY_MY_CLASSES]->getAvailable();
        $getMyGroups     = $options[Definition::KEY_MY_GROUPS]->getAvailable();
        $getOtherClasses = $options[Definition::KEY_OTHER_CLASSES]->getAvailable();
        $getOtherGroups  = $options[Definition::KEY_OTHER_GROUPS]->getAvailable();
        $folderMapper    = $this->getFolderMapper();
        $myFolders       = $folderMapper->createCollection();
        $otherFolders    = $folderMapper->createCollection();
        $folderIdColumn  = 'id';

        if ($getMyClasses || $getMyGroups) {
            // Note: '4' is a hack to mean 'INVALID', to force no group folders to be matched.
            $groupAccessLevel = $getMyGroups ? UserAccess::ACCESS_READ : UserAccess::ACCESS_FULL + 1;
            $myFolders        = $folderMapper->findForUser(
                $viewee,
                $getMyClasses,
                $groupAccessLevel
            )->indexBy($folderIdColumn);
        }

        if ($getOtherClasses) {
            $otherFolders = $folderMapper->findByPredicates(
                $folderMapper->getPredicateBuilder()
                             ->notNull('cc.code')
                             ->isNull('cc.deleted_at')
            )->indexBy($folderIdColumn);
        }

        if ($getOtherGroups) {
            $otherFolders = $otherFolders->mergeUnique(
                $folderMapper->findByPredicates(
                    $folderMapper->getPredicateBuilder()
                                 ->notNull('ug.cid')
                )->indexBy($folderIdColumn),
                $folderIdColumn
            );
        }

        // Ensure other folders don't intersect with my folders
        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()] ?? null,
            $this->getSelectionMapper(),
            $myFolders,
            $otherFolders->diffBy($myFolders, 'id')
        );
    }
}
