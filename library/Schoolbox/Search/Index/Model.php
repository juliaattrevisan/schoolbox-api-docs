<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Index;

use Alaress_Date_Format;
use Schoolbox\Mapper\Traits\Model\CreatedBy;

/**
 * Model for a search index.
 */
class Model
{
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
     * The current relevance of this search index: usually based on date, but
     * might be able to be cached.
     */
    private float $relevance;

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
     * article text.
     */
    private string $fulltextIndex;

    /**
     * The searchable short index for this search index.
     *
     * In practice: this will be short-form content such as a person's name or
     * title.
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
     * @return float
     */
    public function getRelevance(): float
    {
        return $this->relevance;
    }

    /**
     * @param float $relevance
     * @return $this
     */
    public function setRelevance(float $relevance): self
    {
        $this->relevance = $relevance;
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

    /**
     * @return string
     */
    public function getTextIndex(): string
    {
        return $this->textIndex;
    }

    /**
     * @param string $textIndex
     * @return $this
     */
    public function setTextIndex(string $textIndex): self
    {
        $this->textIndex = $textIndex;
        return $this;
    }
}
