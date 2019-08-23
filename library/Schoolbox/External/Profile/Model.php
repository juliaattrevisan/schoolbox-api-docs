<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Profile;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;

    /**
     * @var string
     */
    private $externalId;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $firstname;

    /**
     * @var string
     */
    private $lastname;


    /**
     * @return string $externalId
     */
    public function getExternalId(): string
    {
        return $this->externalId;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @return string|null
     */
    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    /**
     * @return string|null
     */
    public function getLastname(): ?string
    {
        return $this->lastname;
    }


    /**
     * @param string $externalId
     * @return Model
     */
    public function setExternalId(string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    /**
     * @param string|null $title
     * @return Model
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @param string|null $firstname
     * @return Model
     */
    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;
        return $this;
    }

    /**
     * @param string|null $lastname
     * @return Model
     */
    public function setLastname(?string $lastname): self
    {
        $this->lastname = $lastname;
        return $this;
    }
}
