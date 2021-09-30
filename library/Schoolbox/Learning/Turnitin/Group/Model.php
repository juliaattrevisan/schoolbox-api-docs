<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Group;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


    /**
     * @var int|null
     */
    private ?int $submissionBoxId = null;


    /**
     * @var string|null
     */
    private ?string $groupId = null;


    /**
     * @return int|null
     */
    public function getSubmissionBoxId(): ?int
    {
        return $this->submissionBoxId;
    }


    /**
     * @param int|null $submissionBoxId
     * @return $this
     */
    public function setSubmissionBoxId(?int $submissionBoxId): self
    {
        $this->submissionBoxId = $submissionBoxId;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getGroupId(): ?string
    {
        return $this->groupId;
    }


    /**
     * @param string|null $groupId
     * @return $this
     */
    public function setGroupId(?string $groupId): self
    {
        $this->groupId = $groupId;
        return $this;
    }
}
