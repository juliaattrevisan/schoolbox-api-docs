<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFactory;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Selection;
use Schoolbox\Resource\Folder;


/**
 * A factory for building 'Folder' filter objects
 */
abstract class Factory extends AbstractFactory
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
     * @return Folder\Mapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }


    /**
     * @inheritdoc
     */
    protected function buildKeyConverterArgs(Filter\Model $filter)
    {
        $folderIds = (new Collection($this->folderMapper->findAllIds(['deleted' => true])))->indexByCallback(function ($folderId) {
            return $folderId;
        });

        return array_merge(parent::buildKeyConverterArgs($filter), [
            'folderIds' => $folderIds,
        ]);
    }
}
