<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel;

use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Abstract for CourseSpec  + CourseImpl models
 * @method self setUnits(GenericCollection $units)
 */
abstract class AbstractCourse implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var Folder
     */
    private $folder;

    /**
     * @return Folder
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param Folder $folder
     * @return static
     */
    public function setFolder(Folder $folder = null)
    {
        $this->folder = $folder;
        return $this;
    }


    /**
     * @return GenericCollection|AbstractUnit[]
     */
    abstract public function getUnits(): GenericCollection;
}
