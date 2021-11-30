<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Source;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use \Schoolbox\Comms\News\Model as Article;

/**
 * Represents metadata for news articles and where they originated
 * if they came from a third party source
 */
class Model implements ISoftDeletable
{
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * News Article
     */
    private Article $article;


    /**
     * A string to determine where this came from
     *
     * @var string
     */
    private string $sourceName;


    /**
     * An optional identifier that was used in the third party system
     * Used to figure out which third party data spawned this schoolbox news
     *
     * @var string|null
     */
    private ?string $guid;


    /**
     * @return Article
     */
    public function getArticle(): Article
    {
        return $this->article;
    }


    /**
     * @param Article $article
     * @return $this
     *
     */
    public function setArticle(Article $article): self
    {
        $this->article = $article;
        return $this;
    }


    /**
     * @return string
     */
    public function getSourceName(): string
    {
        return $this->sourceName;
    }


    /**
     * @param string $sourceName
     * @return Model
     */
    public function setSourceName(string $sourceName): self
    {
        $this->sourceName = $sourceName;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getGuid(): ?string
    {
        return $this->guid;
    }


    /**
     * @param string|null $guid
     * @return Model
     */
    public function setGuid(?string $guid): self
    {
        $this->guid = $guid;
        return $this;
    }
}
