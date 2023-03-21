<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\LessonPlan\File;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\User;

/**
 * File attached to a Lesson Plan.
 */
class Model implements IFile, ISoftDeletable
{
    use StorableTrait;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * @var \Schoolbox\Learning\Assessment\LessonPlan\Model
     */
    private \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan;


    /**
     * @return \Schoolbox\Learning\Assessment\LessonPlan\Model
     */
    public function getLessonPlan(): \Schoolbox\Learning\Assessment\LessonPlan\Model
    {
        return $this->lessonPlan;
    }


    /**
     * @param \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan
     * @return $this
     */
    public function setLessonPlan(\Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan): self
    {
        $this->lessonPlan = $lessonPlan;
        return $this;
    }


    /**
     * I suppose the Lesson Plan creator remains the owner
     * regardless of who most recently updates it
     *
     * (no transfer of ownership when it comes to Lesson Plan Files)
     *
     * @return User
     */
    public function getOwner(): User
    {
        return $this->lessonPlan->getCreatedBy();
    }
}
