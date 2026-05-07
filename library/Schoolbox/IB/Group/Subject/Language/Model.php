<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Group\Subject\Language;

use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Subject\Model as GroupSubjectModel;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Resource\Folder\Code\Model as FolderCodeModel;

use function array_filter;
use function array_map;
use function array_unique;
use function explode;
use function is_string;

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
     * @var int|null
     */
    private ?int $systemPurposeId = null;

    /**
     * @var GenericCollection|FolderCodeModel[]|null
     */
    private ?GenericCollection $classCode = null;

    /**
     * @return int|null
     */
    public function getSystemPurposeId(): ?int
    {
        return $this->systemPurposeId;
    }

    /**
     * @param int|null $systemPurposeId
     * @return self
     */
    public function setSystemPurposeId(?int $systemPurposeId): self
    {
        $this->systemPurposeId = $systemPurposeId;
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

    /**
     * @return GenericCollection|FolderCodeModel[]
     */
    public function getClassCode(): GenericCollection
    {
        if ($this->classCode === null) {
            $this->classCode = new GenericCollection(FolderCodeModel::class);
        }

        return $this->classCode;
    }

    /**
     * @param GenericCollection|FolderCodeModel[]|string|null $classCode
     * @return self
     */
    public function setClassCode(GenericCollection|string|null $classCode): self
    {
        if ($classCode instanceof GenericCollection) {
            $classCode->assertCollectionOf(FolderCodeModel::class);
            $this->classCode = $classCode;
            return $this;
        }

        $collection = new GenericCollection(FolderCodeModel::class);

        if (is_string($classCode)) {
            $codes = array_unique(array_filter(
                array_map('trim', explode(',', $classCode)),
                static fn(string $code): bool => $code !== ''
            ));

            foreach ($codes as $code) {
                $collection->add((new FolderCodeModel())->setCode($code));
            }
        }

        $this->classCode = $collection;
        return $this;
    }
}
