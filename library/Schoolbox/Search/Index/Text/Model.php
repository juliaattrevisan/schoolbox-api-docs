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
     * The index this component belongs to.
     */
    private \Schoolbox\Search\Index\Model $searchIndex;

    /**
     * Text component for this search index.
     */
    private string $textIndex;

    /**
     * @return \Schoolbox\Search\Index\Model
     */
    public function getSearchIndex(): \Schoolbox\Search\Index\Model
    {
        return $this->searchIndex;
    }

    /**
     * @param \Schoolbox\Search\Index\Model $searchIndex
     * @return $this
     */
    public function setSearchIndex(\Schoolbox\Search\Index\Model $searchIndex): self
    {
        $this->searchIndex = $searchIndex;
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
