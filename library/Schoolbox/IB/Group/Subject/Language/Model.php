<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Group\Subject\Language;

use Schoolbox\IB\Group\Subject\Model as GroupSubjectModel;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Class Model
 * Represents the relationship between an IB group subject, a language, and a folder class code
 *
 * @package Schoolbox\IB\GroupSubject\Language
 */
class Model
{
    use Id;

    /**
     * @var GroupSubjectModel|null
     */
    private ?GroupSubjectModel $groupSubject = null;

    /**
     * @var LanguageModel|null
     */
    private ?LanguageModel $language = null;

    /**
     * @var string|null
     */
    private ?string $classCode = null;

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

    /**
     * @return string|null
     */
    public function getClassCode(): ?string
    {
        return $this->classCode;
    }

    /**
     * @param string|null $classCode
     * @return self
     */
    public function setClassCode(?string $classCode): self
    {
        $this->classCode = $classCode;
        return $this;
    }
}
