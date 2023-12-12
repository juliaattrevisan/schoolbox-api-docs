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
     * The index this component belongs to.
     */
    private \Schoolbox\Search\Index\Model $searchIndex;

    /**
     * Full-text component for this search index.
     */
    private string $fulltextIndex;

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
