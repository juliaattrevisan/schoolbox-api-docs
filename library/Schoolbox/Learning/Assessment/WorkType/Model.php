<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\WorkType;

use Schoolbox\ISoftDeletable;
use Schoolbox\Sequential;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;

class Model implements ISoftDeletable, Sequential
{

    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\CreateCollection;
    use ModelTrait\HasSequence;

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $externalResultType;

    /**
     * @var boolean
     */
    private $showOnGradesPage;

    /** @var bool */
    private $staffOrInstructorVisibleOnly;

    /**
     * @var int
     */
    private $updatedBy;

    /**
     * @return bool
     */
    public function getShowOnGradesPage()
    {
        return $this->isShowOnGradesPage();
    }

    /**
     * @return bool
     */
    public function isShowOnGradesPage()
    {
        return $this->showOnGradesPage;
    }


    /**
     * @param bool $showOnGradesPage
     */
    public function setShowOnGradesPage(bool $showOnGradesPage): void
    {
        $this->showOnGradesPage = $showOnGradesPage;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
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
     * @param string $externalResultType
     * @return static
     */
    public function setExternalResultType($externalResultType)
    {
        $this->externalResultType = $externalResultType;
        return $this;
    }


    /**
     * @return string
     */
    public function getExternalResultType()
    {
        return $this->externalResultType;
    }


    /**
     * Human friendly getter
     *
     * @return bool
     * @throws InsufficientHydrationException
     */
    public function isStaffOrInstructorVisibleOnly(): bool
    {
        if (null === $this->staffOrInstructorVisibleOnly) {
            throw new InsufficientHydrationException('Cannot determine visibility from NULL property');
        }
        return $this->staffOrInstructorVisibleOnly;
    }


    /**
     * Mapper-compatible getter.
     *
     * @return bool
     */
    public function getStaffOrInstructorVisibleOnly(): ?bool
    {
        return $this->staffOrInstructorVisibleOnly;
    }


    /**
     * @param bool $staffOrInstructorVisibleOnly
     * @return $this
     */
    public function setStaffOrInstructorVisibleOnly(bool $staffOrInstructorVisibleOnly): self
    {
        $this->staffOrInstructorVisibleOnly = $staffOrInstructorVisibleOnly;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    /**
     * @param int|null $updatedBy
     * @return $this
     */
    public function setUpdatedBy(?int $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }
}
