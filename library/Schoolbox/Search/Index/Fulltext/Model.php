<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Index\Fulltext;

/**
 * Model for the full-text component of search indices.
 */
class Model
{
    /**
     * Identifier for this search index.
     */
    private string $identifier;

    /**
     * Full-text component for this search index.
     */
    private string $fulltextIndex;

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
