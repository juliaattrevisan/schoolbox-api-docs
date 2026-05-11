<?php

/**
 * IB registration session student subject language model
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student\Subject\Language;

use Schoolbox\IB\Group\Subject\Model as GroupSubjectModel;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Mapper\Traits\Model as ModelTraits;

/**
 * Class Model
 *
 * @package Schoolbox\IB\Registrations\Sessions\Student\Subject\Language
 */
class Model
{
    use ModelTraits\Id;
    use ModelTraits\CreatedAtDate;
    use ModelTraits\UpdatedAtDate;

    /**
     * Registration session student this subject language selection belongs to
     * @var RegistrationSessionStudentModel|null
     */
    private ?RegistrationSessionStudentModel $registrationSessionStudent = null;

    /**
     * IB group subject selected by the student
     * @var GroupSubjectModel|null
     */
    private ?GroupSubjectModel $groupSubject = null;

    /**
     * Language selected for the subject
     * @var LanguageModel|null
     */
    private ?LanguageModel $language = null;

    /**
     * @return RegistrationSessionStudentModel|null
     */
    public function getRegistrationSessionStudent(): ?RegistrationSessionStudentModel
    {
        return $this->registrationSessionStudent;
    }

    /**
     * @param RegistrationSessionStudentModel|null $registrationSessionStudent
     * @return self
     */
    public function setRegistrationSessionStudent(?RegistrationSessionStudentModel $registrationSessionStudent): self
    {
        $this->registrationSessionStudent = $registrationSessionStudent;
        return $this;
    }

    /**
     * @return GroupSubjectModel|null
     */
    public function getGroupSubject(): ?GroupSubjectModel
    {
        return $this->groupSubject;
    }

    /**
     * @param GroupSubjectModel|null $groupSubject
     * @return self
     */
    public function setGroupSubject(?GroupSubjectModel $groupSubject): self
    {
        $this->groupSubject = $groupSubject;
        return $this;
    }

    /**
     * @return LanguageModel|null
     */
    public function getLanguage(): ?LanguageModel
    {
        return $this->language;
    }

    /**
     * @param LanguageModel|null $language
     * @return self
     */
    public function setLanguage(?LanguageModel $language): self
    {
        $this->language = $language;
        return $this;
    }
}
