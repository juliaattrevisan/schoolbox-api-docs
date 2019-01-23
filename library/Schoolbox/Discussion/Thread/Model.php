<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

/**
 * Model for discussion threads.
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * @var bool
     */
    private $open;


    /**
     * @var Context\Model|null
     */
    private $context;


    /**
     * @return bool
     */
    public function getOpen(): ?bool
    {
        return $this->open;
    }


    /**
     * @param bool $open
     * @return static
     */
    public function setOpen(bool $open): self
    {
        $this->open = $open;
        return $this;
    }


    /**
     * @return Context\Model|null
     */
    public function getContext(): ?Context\Model
    {
        return $this->context;
    }


    /**
     * @param Context\Model|null $context
     * @return static
     */
    public function setContext(?Context\Model $context): self
    {
        $this->context = $context;
        return $this;
    }
}
