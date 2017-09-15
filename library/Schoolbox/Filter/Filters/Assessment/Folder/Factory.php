<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder;

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
