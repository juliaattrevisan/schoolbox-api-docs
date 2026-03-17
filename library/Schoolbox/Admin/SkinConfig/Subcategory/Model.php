<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SkinConfig\Subcategory;

use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Admin\SkinConfig\Category;

class Model
{
    use ModelTrait\Id;

    private ?string $name;

    private Category\Model $category;

    /**
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param ?string $name
     * @return Model
     */
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return Category\Model
     */
    public function getCategory(): Category\Model
    {
        return $this->category;
    }

    /**
     * @param Category\Model $category
     * @return Model
     */
    public function setCategory(Category\Model $category): self
    {
        $this->category = $category;
        return $this;
    }
}
