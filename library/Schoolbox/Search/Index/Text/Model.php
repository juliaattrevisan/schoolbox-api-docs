<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Index\Text;

/**
 * Model for the basic text component of search indices.
 */
class Model
{
    /**
     * Identifier for this search index.
     */
    private string $identifier;

    /**
     * Text component for this search index.
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
