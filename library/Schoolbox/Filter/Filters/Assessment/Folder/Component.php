<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Resource\Folder;


class Component extends Filter\BaseComponent
{

    /**
     * @var GenericCollection|Folder\Model[]
     */
    private $folders;


    /**
     * @param Filter\IDefinition                          $definition
     * @param User                                        $user
     * @param Filter\Page\Model                           $pageFilter
     * @param GenericCollection|Filter\Selection\Model[]  $userSelections
     * @param Filter\Selection\Mapper                     $filterSelectionMapper
     * @param Collection|GenericCollection|Folder\Model[] $folders
     */
    public function __construct(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        GenericCollection $userSelections,
        Filter\Selection\Mapper $filterSelectionMapper,
        GenericCollection $folders
    ) {
        parent::__construct($definition, $user, $pageFilter, $userSelections, $filterSelectionMapper);
        $this->folders = $folders;
    }


    /**
     * @inheritdoc
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions
     * @throws \InvalidArgumentException If option key unrecognised
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions)
    {
        $availableSelections = $this->getFilterSelectionMapper()->createCollection();
        $roleType            = $this->getUser()->getRole()->getType();
        $isUserConfigurable  = $this->getPageFilter()->getUserConfigurable();

        foreach ($abstractOptions as $option) {
            // has admin configured the filter to be user-configurable, but that this option should not be available?
            if ($isUserConfigurable && !$option->getAvailable()) {
                continue;
            }

            // did the admin configure the abstract option to be selected by default for the specified role?
            $selected = $option->getDefaults()[$roleType]->getSelected();

            foreach ($this->folders as $folder) {
                $key = (string)$folder->getId();

                if (!$availableSelections->offsetExists($key)) {
                    // Does the folder not yet exist in selections ?
                    $availableSelections[$key] = $this->createSelection($key, $selected);
                } elseif ($selected) {
                    // Does it exist, ensure it is selected if abstract option is selected
                    $availableSelections[$key]->setSelected(true);
                }
            }
        }
        return $availableSelections;
    }


    /**
     * @inheritdoc
     */
    public function toContext()
    {
        return array_merge(parent::toContext(), [
            'folders' => $this->folders,
        ]);
    }
}
