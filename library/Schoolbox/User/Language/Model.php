<?php

namespace Schoolbox\User\Language;

use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\User;

class Model
{
    use ModelTraits\Id;
    use ModelTraits\CreateCollection;

    private User $user;

    private string $language;

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
     * Get language
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * @param string $language
     * @return $this
     */
    public function setLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }

}
