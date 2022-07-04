<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\ReportExport;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * Report Options has this structure
     */
    public const DEFAULT_OPTIONS = [
        'terms' => [],
        'yearLevels' => [],
        'campuses' => [],
        'students' => [],
        'summary' => [
            'active' => 0,
            'heading' => '',
            'workType' => [],
            'showOverallGrade' => 0,
            'showComments' => 0,
            'showRubrics' => 0
        ],
        'class' => [
            'active' => 0,
            'workType' => [],
            'showOverallGrade' => 0,
            'showLearningLevels' => 0,
            'showCohort' => 0,
            'showCapabilityGrades' => 0,
            'showProgression' => 0,
            'showComments' => 0,
            'showRubrics' => 0
        ]
    ];

    private ?string $title = null;
    private ?string $introduction = null;
    private ?\Schoolbox\Resource\Storage\Model $logo = null;
    private ?string $options = null;
    private ?\Schoolbox\User $updatedBy = null;


    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }


    /**
     * @param string|null $title
     * @return $this
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getIntroduction(): ?string
    {
        return $this->introduction;
    }


    /**
     * @param string|null $introduction
     * @return $this
     */
    public function setIntroduction(?string $introduction): self
    {
        $this->introduction = $introduction;
        return $this;
    }


    /**
     * @return \Schoolbox\Resource\Storage\Model|null
     */
    public function getLogo(): ?\Schoolbox\Resource\Storage\Model
    {
        return $this->logo;
    }


    /**
     * @param \Schoolbox\Resource\Storage\Model|null $logo
     * @return $this
     */
    public function setLogo(?\Schoolbox\Resource\Storage\Model $logo): self
    {
        $this->logo = $logo;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getOptions(): ?string
    {
        return $this->options;
    }


    /**
     * @param string|null $options
     * @return $this
     */
    public function setOptions(?string $options): self
    {
        $this->options = $options;
        return $this;
    }


    /**
     * @return User|null
     */
    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }


    /**
     * @param User|null $updatedBy
     * @return $this
     */
    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }
}
