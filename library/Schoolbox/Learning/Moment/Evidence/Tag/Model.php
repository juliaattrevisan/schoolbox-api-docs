<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Tag;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

/**
 * Model for learning moment tags.
 */
class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\UpdatedBy;

    /**
     * @var string
     */
    private $name;

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return static
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
