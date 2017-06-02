<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Resource\Folder;


/**
 * A factory for building 'Folder' filter objects
 */
abstract class Factory extends Filter\AbstractFactory
{

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;


    /**
     * @param Filter\Mapper                      $filterMapper
     * @param Filter\Page\Mapper                 $pageMapper
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param Folder\Mapper                      $folderMapper
     */
    public function __construct(
        Filter\Mapper $filterMapper,
        Filter\Page\Mapper $pageMapper,
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        Folder\Mapper $folderMapper
    ) {
        parent::__construct($filterMapper, $pageMapper, $optionMapper, $defaultMapper, $selectionMapper);
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
