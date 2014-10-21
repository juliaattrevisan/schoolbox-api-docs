<?php
/**
 * Model for submission box grade groups.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Grade\Group;

use Schoolbox\Collection;
use Schoolbox\Assessment\Component\SubmissionBox\Grade\Model as GradeModel;

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
     * @var Collection|GradeModel[]
     */
    protected $gradeLevels;

    /**
     * Constructor - sets up grade level collection.
     */
    public function __construct()
    {
        $this->setGradeLevels(new Collection());
    }

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
     * @return GradeModel[]|Collection
     */
    public function getGradeLevels()
    {
        return $this->gradeLevels;
    }

    /**
     * @param  GradeModel[]|Collection $gradeLevels
     * @return $this
     */
    public function setGradeLevels($gradeLevels)
    {
        $this->gradeLevels = $gradeLevels;
        return $this;
    }

    /**
     * @param  GradeModel $level
     * @return $this
     */
    public function addGradeLevel(GradeModel $level)
    {
        $this->getGradeLevels()->add($level);
    }
}
