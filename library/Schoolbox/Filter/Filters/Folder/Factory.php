<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Folder;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\Filter\IRunTimeOptions;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Selection;
use Schoolbox\User;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;


/**
 * A factory for building FolderFilters
 *
 * @todo
 */
class Factory extends AbstractFilterFactory
{

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;


    /**
     * @param Filter\Mapper    $filterMapper
     * @param Option\Mapper    $optionMapper
     * @param Defaults\Mapper  $defaultMapper
     * @param Selection\Mapper $selectionMapper
     * @param Folder\Mapper    $folderMapper
     */
    public function __construct(
        Filter\Mapper $filterMapper,
        Option\Mapper $optionMapper,
        Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        Folder\Mapper $folderMapper
    ) {
        parent::__construct($filterMapper, $optionMapper, $defaultMapper, $selectionMapper);
        $this->folderMapper = $folderMapper;
    }


    /**
     * Build a filter definition, ready to use in the admin pages
     *
     * No run-time options or user-selections relevant.
     *
     * @param Filter\Model $filter
     * @return IFilterDefinition
     */
    public function buildDefinition(Filter\Model $filter)
    {
        return new Definition(
            $filter,
            $this->getFilterOptionMapper(),
            $this->getFilterDefaultMapper()
        );
    }


    /**
     * @inheritdoc
     * @param RunTimeOptions $runTimeOptions
     * @throws Mapper\Exception
     * @throws \InvalidArgumentException If wrong run-time options passed
     */
    public function buildComponent(User $user, $page, IRunTimeOptions $runTimeOptions = null)
    {
        if (!$runTimeOptions instanceof RunTimeOptions) {
            $type  = gettype($runTimeOptions);
            $found = 'object' === $type ? get_class($runTimeOptions) : $type;
            $msg   = 'Options must be an instance of ' . RunTimeOptions::class . ". Found: $found";
            throw new \InvalidArgumentException($msg);
        }

        // Divide folders into Classes and Groups (not mutually exclusive)
        $folders = $runTimeOptions->getFolders();

        // Does the folder have class codes ?
        $classes = $folders->filter(function (Folder\Model $folder) {
            return $folder->getCodes()->count();
        })->indexBy('id');

        // Does the folder have a group ?
        $groups = $folders->filter(function (Folder\Model $folder) {
            return $folder->getGroup();
        })->indexBy('id');

        $filter          = $this->getFilterMapper()->findByClass(Definition::class);
        $definition      = $this->buildDefinition($filter);
        $savedSelections = $this->getSavedSelections($filter, $user, $page);

        return new Component(
            $definition,
            $user,
            $page,
            $savedSelections,
            $this->getFilterSelectionMapper(),
            $classes,
            $groups
        );
    }


    /**
     * Returns a key converter, able to convert between concrete keys and the entities they represent
     *
     * @return KeyConverter
     */
    public function buildKeyConverter()
    {
        $filter    = $this->getFilterMapper()->findByClass(Definition::class);
        $folderIds = (new Collection($this->folderMapper->findAllIds()))->indexByCallback(function ($folderId) {
            return $folderId;
        });

        return new KeyConverter($filter, $folderIds);
    }
}
