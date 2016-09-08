<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification\Unit;

use Alaress_Date_NullDate;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationModel;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Data domain model for Unit Specifications.
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var CourseSpecificationModel
     */
    protected $course;

    /**
     * @var FolderModel
     */
    protected $folder;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->createdAt = new Alaress_Date_NullDate();
        $this->updatedAt = new Alaress_Date_NullDate();
        $this->deletedAt = new Alaress_Date_NullDate();
    }
}
