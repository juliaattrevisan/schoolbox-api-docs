<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\Readable;

use InvalidArgumentException;
use Schoolbox\Collection;
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
        $myClasses       = $folderMapper->createCollection();
        $myGroups        = $folderMapper->createCollection();
        $otherClasses    = $folderMapper->createCollection();
        $otherGroups     = $folderMapper->createCollection();
        $folderIdColumn  = 'id';

        // Ensure my classes and groups are separate from one another
        if ($getMyClasses) {
            // Note: UserAccess::ACCESS_FULL is a hack to mean 'INVALID',
            // to force no group folders to be matched.
            $myClasses = $folderMapper->findForUser(
                $viewee,
                true,
                UserAccess::ACCESS_FULL + 1
            )->indexBy($folderIdColumn);
        }

        if ($getMyGroups) {
            $myGroups = $folderMapper->findForUser($viewee, false)->indexBy($folderIdColumn);
        }

        // Ensure other folders don't intersect with my folders
        if ($getOtherClasses) {
            $otherClasses = $folderMapper->findByPredicates(
                $folderMapper->getPredicateBuilder()
                             ->notNull('cc.code')
                             ->isNull('cc.deleted_at')
            )->indexBy($folderIdColumn);
        }

        if ($getOtherGroups) {
            $otherGroups = $folderMapper->findByPredicates(
                $folderMapper->getPredicateBuilder()
                             ->notNull('cc.code')
                             ->isNull('cc.deleted_at')
            )->indexBy($folderIdColumn);
        }

        // Ensure other folders don't intersect with my folders
        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()] ?? null,
            $this->getSelectionMapper(),
            $folderMapper->createCollection(),
            $myClasses,
            $myGroups,
            $otherClasses->diffBy($myClasses, $folderIdColumn)->diffBy($myGroups, $folderIdColumn),
            $otherGroups->diffBy($myClasses, $folderIdColumn)->diffBy($myGroups, $folderIdColumn)
        );
    }


    /**
     * @inheritDoc
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        $folderIds = (new Collection($this->getFolderMapper()->findAllIds(['deleted' => true])))
            ->filter(function ($folderId) use ($user) {
                // Too heavy to filter by folder access
                // Filter instead by whether the folder fits in the selections
                return true;
//                return $this->getAccess()->getFolderAccess($user, $folderId) >= UserAccess::ACCESS_WRITE;
            })
            ->indexByCallback(static function ($folderId) {
                return $folderId;
            });

        // Inject the folder mapper in here
        return new KeyConverter($filter, $folderIds, $this->getAccess(), $this->getFolderMapper());
    }
}
