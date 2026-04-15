<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Reporting\Feedback;

use Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Model extends AbstractFeedback
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * @var \Schoolbox\Learning\Assessment\DataModel\Reporting\Model|null
     */
    private ?\Schoolbox\Learning\Assessment\DataModel\Reporting\Model $reporting = null;


    /**
     * @var string|null
     */
    private ?string $instructorMark = null;


    /**
     * @var int|null
     */
    private ?int $publishedBy = null;


    /* Getters/Setters
     */

    /**
     * @return \Schoolbox\Learning\Assessment\DataModel\Reporting\Model|null
     */
    public function getReporting(): ?\Schoolbox\Learning\Assessment\DataModel\Reporting\Model
    {
        return $this->reporting;
    }


    /**
     * @param \Schoolbox\Learning\Assessment\DataModel\Reporting\Model|null $reporting
     * @return $this
     */
    public function setReporting(?\Schoolbox\Learning\Assessment\DataModel\Reporting\Model $reporting): self
    {
        $this->reporting = $reporting;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getHumanReadableInstructorMark(): ?string
    {
        return $this->instructorMark;
    }


    /**
     * @param string|null $instructorMark
     * @return $this
     */
    public function setHumanReadableInstructorMark(?string $instructorMark): self
    {
        $this->instructorMark = $instructorMark;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getPublishedBy(): ?int
    {
        return $this->publishedBy;
    }


    /**
     * @param int|null $publishedBy
     * @return $this
     */
    public function setPublishedBy(?int $publishedBy): self
    {
        $this->publishedBy = $publishedBy;
        return $this;
    }
}
