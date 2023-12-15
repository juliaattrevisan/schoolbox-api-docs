<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Index;

use Alaress_Date_Format;
use Schoolbox\Mapper\Traits\Model\CreatedBy;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Model for a search index.
 */
class Model
{
    /**
     * Search indices have a standard ID field.
     */
    use Id;

    /**
     * Search indices may have been created by a particular user.
     */
    use CreatedBy;

    /**
     * Unique identifier for this search index.
     */
    private string $identifier;

    /**
     * Title of this search index.
     */
    private string $title;

    /**
     * The type of search index which this is.
     */
    private string $type;

    /**
     * The date of this search index.
     */
    private ?Alaress_Date_Format $date;

    /**
     * Is this search index's relevance affected by its date?
     *
     * Some types of index (users and folders) are unaffected, and maintain a
     * constant relevance independent of their date: other types' relevance will
     * decay the further away their date is from the current date.
     */
    private bool $isRelevanceDateAffected;

    /**
     * Which folder (if any) does this search index belong within?
     *
     * (Unmapped: the folder's specifics don't matter, just its existence.)
     */
    private ?int $folderId;

    /**
     * The searchable full-text index for this search index.
     *
     * In practice: this will be long-form content such as a description or
     * article text, and will be searched with a
     * `MATCH (column) AGAINST (:keyword)` query.
     */
    private string $fulltextIndex;

    /**
     * The searchable short index for this search index.
     *
     * In practice: this will be short-form content such as a person's name or
     * title, and will be searched with a `column LIKE :keyword` query.
     */
    private string $textIndex;

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param string $identifier
     * @return $this
     */
    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getDate(): ?Alaress_Date_Format
    {
        return $this->date;
    }

    /**
     * @param Alaress_Date_Format|null $date
     * @return $this
     */
    public function setDate(?Alaress_Date_Format $date): self
    {
        $this->date = $date;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsRelevanceDateAffected(): bool
    {
        return $this->isRelevanceDateAffected;
    }

    /**
     * @param bool $isRelevanceDateAffected
     * @return $this
     */
    public function setIsRelevanceDateAffected(bool $isRelevanceDateAffected): self
    {
        $this->isRelevanceDateAffected = $isRelevanceDateAffected;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getFolderId(): ?int
    {
        return $this->folderId;
    }

    /**
     * @param int|null $folderId
     * @return $this
     */
    public function setFolderId(?int $folderId): self
    {
        $this->folderId = $folderId;
        return $this;
    }

    /**
     * @return string
     */
    public function getFulltextIndex(): string
    {
        return $this->fulltextIndex;
    }

    /**
     * @param string $fulltextIndex
     * @return $this
     */
    public function setFulltextIndex(string $fulltextIndex): self
    {
        $this->fulltextIndex = $fulltextIndex;
        return $this;
    }
}
