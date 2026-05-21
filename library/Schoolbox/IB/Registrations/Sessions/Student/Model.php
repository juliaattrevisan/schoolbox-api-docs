<?php

/**
 * IB Registration Session Student Model
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Subject\Model as GroupSubjectModel;
use Schoolbox\IB\Registrations\Sessions\Model as SessionModel;
use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\User;

/**
 * Class Model
 *
 * @package Schoolbox\IB\Registrations\Sessions\Student
 */
class Model
{
    use ModelTraits\Id;
    use ModelTraits\CreatedAtDate;
    use ModelTraits\UpdatedAtDate;

    public const int STATUS_PREPARATION = 0;
    public const int STATUS_UNCONFIRMED = 1;
    public const int STATUS_CONFIRMED = 2;
    public const int STATUS_IN_PROGRESS = 3;
    public const int STATUS_FAILED = 4;
    public const int STATUS_REGISTERED = 5;

    public const array STATUSES = [
        [
            'value' => self::STATUS_PREPARATION,
            'key' => 'preparation',
            'label' => 'Preparation',
        ],
        [
            'value' => self::STATUS_UNCONFIRMED,
            'key' => 'unconfirmed',
            'label' => 'Unconfirmed',
        ],
        [
            'value' => self::STATUS_CONFIRMED,
            'key' => 'confirmed',
            'label' => 'Confirmed',
        ],
        [
            'value' => self::STATUS_IN_PROGRESS,
            'key' => 'inProgress',
            'label' => 'In Progress',
        ],
        [
            'value' => self::STATUS_FAILED,
            'key' => 'failed',
            'label' => 'Failed',
        ],
        [
            'value' => self::STATUS_REGISTERED,
            'key' => 'registered',
            'label' => 'Registered',
        ],
    ];

    /**
     * Registration status
     * @var int
     */
    private int $status = self::STATUS_PREPARATION;

    /**
     * Candidate details confirmation state
     * @var bool
     */
    private bool $candidateConfirmed = false;

    /**
     * Candidate details confirmation state update date
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $candidateConfirmedAt = null;

    /**
     * User who last updated the candidate details confirmation state
     * @var User|null
     */
    private ?User $candidateConfirmedBy = null;

    /**
     * Subject details confirmation state
     * @var bool
     */
    private bool $subjectConfirmed = false;

    /**
     * Subject details confirmation state update date
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $subjectConfirmedAt = null;

    /**
     * User who last updated the subject details confirmation state
     * @var User|null
     */
    private ?User $subjectConfirmedBy = null;

    /**
     * Registration session this student belongs to
     * @var SessionModel|null
     */
    private ?SessionModel $registrationSession = null;

    /**
     * Student user record
     * @var User|null
     */
    private ?User $user = null;

    /**
     * IB group subjects matched through the student's class registrations.
     * @var GenericCollection|GroupSubjectModel[]|null
     */
    private ?GenericCollection $ibGroupSubjects = null;

    /**
     * @return SessionModel|null
     */
    public function getRegistrationSession(): ?SessionModel
    {
        return $this->registrationSession;
    }

    /**
     * @param SessionModel|null $registrationSession
     * @return self
     */
    public function setRegistrationSession(?SessionModel $registrationSession): self
    {
        $this->registrationSession = $registrationSession;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param int $status
     * @return self
     */
    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCandidateConfirmed(): bool
    {
        return $this->candidateConfirmed;
    }

    /**
     * @param bool $candidateConfirmed
     * @return self
     */
    public function setCandidateConfirmed(bool $candidateConfirmed): self
    {
        $this->candidateConfirmed = $candidateConfirmed;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getCandidateConfirmedAt(): ?Alaress_Date_Format
    {
        return $this->candidateConfirmedAt;
    }

    /**
     * @param Alaress_Date_Format|null $candidateConfirmedAt
     * @return self
     */
    public function setCandidateConfirmedAt(?Alaress_Date_Format $candidateConfirmedAt): self
    {
        $this->candidateConfirmedAt = $candidateConfirmedAt;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getCandidateConfirmedBy(): ?User
    {
        return $this->candidateConfirmedBy;
    }

    /**
     * @param User|null $candidateConfirmedBy
     * @return self
     */
    public function setCandidateConfirmedBy(?User $candidateConfirmedBy): self
    {
        $this->candidateConfirmedBy = $candidateConfirmedBy;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSubjectConfirmed(): bool
    {
        return $this->subjectConfirmed;
    }

    /**
     * @param bool $subjectConfirmed
     * @return self
     */
    public function setSubjectConfirmed(bool $subjectConfirmed): self
    {
        $this->subjectConfirmed = $subjectConfirmed;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getSubjectConfirmedAt(): ?Alaress_Date_Format
    {
        return $this->subjectConfirmedAt;
    }

    /**
     * @param Alaress_Date_Format|null $subjectConfirmedAt
     * @return self
     */
    public function setSubjectConfirmedAt(?Alaress_Date_Format $subjectConfirmedAt): self
    {
        $this->subjectConfirmedAt = $subjectConfirmedAt;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getSubjectConfirmedBy(): ?User
    {
        return $this->subjectConfirmedBy;
    }

    /**
     * @param User|null $subjectConfirmedBy
     * @return self
     */
    public function setSubjectConfirmedBy(?User $subjectConfirmedBy): self
    {
        $this->subjectConfirmedBy = $subjectConfirmedBy;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     * @return self
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return GenericCollection|GroupSubjectModel[]
     */
    public function getIbGroupSubjects(): GenericCollection
    {
        return $this->ibGroupSubjects ?? new GenericCollection(GroupSubjectModel::class);
    }

    /**
     * @param GenericCollection|GroupSubjectModel[] $ibGroupSubjects
     * @return self
     */
    public function setIbGroupSubjects(GenericCollection $ibGroupSubjects): self
    {
        $ibGroupSubjects->assertCollectionOf(GroupSubjectModel::class);
        $this->ibGroupSubjects = $ibGroupSubjects;
        return $this;
    }
}
