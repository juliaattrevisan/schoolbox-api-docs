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
use Schoolbox\Resource\Folder\Model as FolderModel;
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
        // Folders: just whatever is in the URL overrides for this filter
        // (filtered to groups/classes only, and then by if user has read perms)
        $thisOverrides = $overrides[$pageFilter->getFilter()->getId()] ?? null;
        if ($thisOverrides instanceof Collection) {
            $folders = $this->getFolderMapper()
                ->findByColumn('id', $thisOverrides->getPropertyValues('key'))
                ->filter(function (FolderModel $folder) use ($user) {
                    return
                        ($folder->isGroup() || $folder->isClass())
                        && $this->getAccess()->getFolderAccess($user, $folder->getId()) >= UserAccess::ACCESS_READ;
                });
        } else {
            $folders = $this->getFolderMapper()->createCollection();
        }

        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $thisOverrides,
            $this->getSelectionMapper(),
            $folders->sortBy('name', 'ASC')
        );
    }

    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        // Don't care about contents yet
        return new KeyConverter($filter, new Collection(), $this->getAccess());
    }
}
