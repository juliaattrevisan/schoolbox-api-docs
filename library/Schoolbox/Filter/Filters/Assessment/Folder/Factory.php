<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Permission\Access\Access;
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

    /** @var Access */
    private $access;


    /**
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param Folder\Mapper                      $folderMapper
     * @param Access                             $access
     */
    public function __construct(
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        Folder\Mapper $folderMapper,
        Access $access
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->folderMapper = $folderMapper;
        $this->access       = $access;
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        $folderIds = (new Collection($this->getFolderMapper()->findAllIds(['deleted' => true])))
            ->indexByCallback(function ($folderId) {
                return $folderId;
            });
        return new KeyConverter($filter, $folderIds, $this->getAccess());
    }


    /**
     * @return Folder\Mapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }


    /**
     * @return Access
     */
    protected function getAccess(): Access
    {
        return $this->access;
    }
}
