<?php

namespace Schoolbox\User\Nationality;

use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\User;

class Model
{
    use ModelTraits\Id;
    use ModelTraits\CreateCollection;

    private User $user;

    private string $nationality;

    /**
     * Get user
     *
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Set user
     *
     * @param User $user
     * @return $this
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get nationality
     * @return string
     */
    public function getNationality(): string
    {
        return $this->nationality;
    }

    /**
     * @param string $nationality
     * @return $this
     */
    public function setNationality(string $nationality): self
    {
        $this->nationality = $nationality;
        return $this;
    }
}
