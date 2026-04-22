<?php

/**
 * IB System Purpose Model
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\SystemPurpose;

use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\IB\Category\Model as CategoryModel;

/**
 * Class Model
 *
 * @package Schoolbox\IB\SystemPurpose
 */
class Model
{
    use Id;

    /**
     * The IB category this system purpose belongs to
     * @var CategoryModel|null
     */
    private ?CategoryModel $category = null;

    /**
     * System purpose name
     * @var string|null
     */
    private ?string $name = null;

    /**
     * @return CategoryModel|null
     */
    public function getCategory(): ?CategoryModel
    {
        return $this->category;
    }

    /**
     * @param CategoryModel|null $category
     * @return self
     */
    public function setCategory(?CategoryModel $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     * @return self
     */
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
