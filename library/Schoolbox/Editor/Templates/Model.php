<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

 declare(strict_types=1);

namespace Schoolbox\Editor\Templates;

use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\CreatedBy;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedBy;

class Model
{
    use Id;
    use CreatedAtDate;
    use CreatedBy;
    use UpdatedAtDate;
    use UpdatedBy;

    /**
     * @var int
     */
    private int $categoryId;

    /**
     * @var string
     */
    private string $title;

    /**
     * @var string
     */
    private string $description;

    /**
     * @var string
     */
    private string $body;

    /**
     * @return int
     */
    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    /**
     * @param int $categoryId
     * @return self
     */
    public function setCategoryId(int $categoryId): self
    {
        $this->categoryId = $categoryId;
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
     *@return self
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return self
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @param string $body
     * @return self
     */
    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }
}
