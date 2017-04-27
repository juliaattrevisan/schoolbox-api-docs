<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Folder;

use Schoolbox\Filter\IRunTimeOptions;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;

/**
 * For now, folder lists are dynamically generated
 * In the future, the architecture can support admins configuring only sub-sets to be available for each page
 */
class RunTimeOptions implements IRunTimeOptions
{

    /**
     * @var GenericCollection|Folder\Model[]
     */
    private $folders;

    /**
     * @param GenericCollection|Folder\Model[] $folders
     */
    public function __construct(GenericCollection $folders)
    {
        $this->folders = $folders;
    }


    /**
     * @return GenericCollection|Folder\Model[]
     */
    public function getFolders()
    {
        return $this->folders;
    }
}
