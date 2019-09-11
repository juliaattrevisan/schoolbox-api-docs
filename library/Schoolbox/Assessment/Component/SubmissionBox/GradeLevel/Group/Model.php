<?php
/**
 * Model for submission box grade groups.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group;

use Schoolbox\Collection;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Model as GradeLevelModel;

class Model
{
    /**
     * Primary key
     *
     * @var int
     */
    protected $id;

    /**
     * Name of this group
     *
     * @var string
     */
    protected $name;

    /**
     * Collection of grade levels that belong to this group
     *
     * @var Collection|GradeLevelModel[]
     */
    protected $gradeLevels;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  int   $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param  string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return GradeLevelModel[]|Collection
     */
    public function getGradeLevels()
    {
        return $this->gradeLevels;
    }

    /**
     * @param  GradeLevelModel[]|Collection $gradeLevels
     * @return $this
     */
    public function setGradeLevels($gradeLevels)
    {
        $this->gradeLevels = $gradeLevels;
        return $this;
    }

    /**
     * @param  GradeLevelModel $level
     * @return $this
     */
    public function addGradeLevel(GradeLevelModel $level)
    {
        // Don't add more than once
        $this->getGradeLevels()->setOffset($level->getId(), $level);
        return $this;
    }
}
