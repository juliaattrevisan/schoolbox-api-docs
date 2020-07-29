<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\YearLevel;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreateCollection;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\HasSequence;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Sequential;

/**
 * A 'YearLevel' is an educational stage, ie, a subdivision of formal learning,
 * typically covering early childhood education, primary education, secondary education and tertiary (or higher) education.
 *
 * Conceptual examples include "Prep", "2", "year 6", "post-grad" etc.
 *
 * These should be considered distinct, eg "Year 3/4" would generally be represented by some other entity
 * having relationships to both a "Year 3" and "Year 4" YearLevel/Model.
 */
class Model implements ISoftDeletable, Sequential
{
    use CreateCollection;
    use Id;
    use HasSequence;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var string */
    private $name;

    /** @var int */
    private $overallGrade;

    /** @var bool */
    private $showOverallGrade;

    /** @var bool */
    private $displayGradePage;

    /**
     * Grade Page: Class Results
     * @var bool
     */
    private $displayAssessmentGrade;

    /**
     * Grade Page: Learning Levels
     * @var bool
     */
    private $displayRubricGrade;

    /**
     * Grade Page: Capability Marks
     * @var bool
     */
    private $displayCapabilityGrade;

    /**
     * Grade Page: Progression
     * @var bool
     */
    private $displayProgressionGrade;

    /**
     * @var bool
     */
    private $showRubricPoints;

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return static
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getOverallGrade(): ?int
    {
        return $this->overallGrade;
    }

    /**
     * @param mixed $overallGrade
     * @return static
     */
    public function setOverallGrade($overallGrade): self
    {
        $this->overallGrade = is_numeric($overallGrade) ? (int)$overallGrade : null;
        return $this;
    }

    /**
     * @return bool
     */
    public function getShowOverallGrade(): bool
    {
        return $this->showOverallGrade;
    }

    /**
     * @param bool $showOverallGrade
     * @return static
     */
    public function setShowOverallGrade(bool $showOverallGrade): self
    {
        $this->showOverallGrade = $showOverallGrade;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDisplayGradePage(): bool
    {
        return $this->displayGradePage;
    }

    /**
     * @param bool $displayGradePage
     * @return static
     */
    public function setDisplayGradePage(bool $displayGradePage): self
    {
        $this->displayGradePage = $displayGradePage;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDisplayAssessmentGrade(): bool
    {
        return $this->displayAssessmentGrade;
    }

    /**
     * @param bool $displayAssessmentGrade
     * @return static
     */
    public function setDisplayAssessmentGrade($displayAssessmentGrade): self
    {
        $this->displayAssessmentGrade = $displayAssessmentGrade;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDisplayRubricGrade(): bool
    {
        return $this->displayRubricGrade;
    }

    /**
     * @param bool $displayRubricGrade
     * @return static
     */
    public function setDisplayRubricGrade($displayRubricGrade): self
    {
        $this->displayRubricGrade = $displayRubricGrade;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDisplayCapabilityGrade(): bool
    {
        return $this->displayCapabilityGrade;
    }

    /**
     * @param bool $displayCapabilityGrade
     * @return static
     */
    public function setDisplayCapabilityGrade($displayCapabilityGrade): self
    {
        $this->displayCapabilityGrade = $displayCapabilityGrade;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDisplayProgressionGrade(): bool
    {
        return $this->displayProgressionGrade;
    }

    /**
     * @param bool $displayProgressionGrade
     * @return static
     */
    public function setDisplayProgressionGrade($displayProgressionGrade): self
    {
        $this->displayProgressionGrade = $displayProgressionGrade;
        return $this;
    }

    /**
     * @return bool
     */
    public function getShowRubricPoints(): bool
    {
        return $this->showRubricPoints;
    }

    /**
     * @param bool $showRubricPoints
     * @return static
     */
    public function setShowRubricPoints($showRubricPoints): self
    {
        $this->showRubricPoints = $showRubricPoints;
        return $this;
    }
}
