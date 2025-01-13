<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Profile;

use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Resource\Storage;

class Model
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;

    /**
     * @var string|null
     */
    private ?string $externalId = null;


    /**
     * @var string|null
     */
    private ?string $title = null;


    /**
     * @var string|null
     */
    private ?string $firstname = null;


    /**
     * @var string|null
     */
    private ?string $firstnamePreferred = null;


    /**
     * @var string|null
     */
    private ?string $lastname = null;


    /**
     * Portrait images are stored in storage now
     *
     * @var Storage\Model|null
     */
    private ?Storage\Model $portrait = null;

    /**
     * A json string corresponding to usually-idiosyncratic connector-specific data
     * (stuff like portrait ids, random misc ids, etc)
     *
     * Each connector will implement a metadata payload specially
     *
     * @var array|null
     */
    private ?array $metadata = null;


    /**
     * @return string $externalId
     */
    public function getExternalId(): string
    {
        return $this->externalId;
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
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
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
     * @return string|null
     */
    public function getFirstname(): ?string
    {
        return $this->firstname;
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
     * @return string|null
     */
    public function getFirstnamePreferred(): ?string
    {
        return $this->firstnamePreferred;
    }


    /**
     * @param string|null $firstnamePreferred
     * @return Model
     */
    public function setFirstnamePreferred(?string $firstnamePreferred): self
    {
        $this->firstnamePreferred = $firstnamePreferred;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getLastname(): ?string
    {
        return $this->lastname;
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


    /**
     * @return Storage\Model|null
     */
    public function getPortrait(): ?Storage\Model
    {
        return $this->portrait;
    }


    /**
     * @param Storage\Model|null $portrait
     * @return self
     */
    public function setPortrait(?Storage\Model $portrait): self
    {
        $this->portrait = $portrait;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }


    /**
     * @param array|null $metadata
     * @return Model
     */
    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
}
