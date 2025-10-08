<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\ImportQueue;

use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

class Model
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;

    /**
     * User
     */
    private User $user;


    /**
     * @var string|null
     */
    private ?string $strats = null;


    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }


    /**
     * @param User $user
     * @return $this
     *
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getStrats(): ?string
    {
        return $this->strats;
    }


    /**
     * @param string|null $strats
     * @return $this
     */
    public function setStrats(?string $strats): self
    {
        $this->strats = $strats;
        return $this;
    }
}
