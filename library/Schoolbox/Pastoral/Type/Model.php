<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Type;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\HasSequence;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Pastoral\DocumentClassification\Model as DocumentClassification;
use Schoolbox\Pastoral;
use DomainException;
use Schoolbox\Sequential;

class Model implements ISoftDeletable, Sequential
{
    use Id;
    use DeletedAtDate;
    use HasSequence;

    /** @var string */
    protected $name;

    /** @var DocumentClassification */
    protected $documentClassification;

    /** @var string */
    protected $externalCode;

    /** @var GenericCollection|Pastoral\SubType\Model[] */
    private $subTypes;


    public function __construct()
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->subTypes = new GenericCollection(Pastoral\SubType\Model::class);
    }


    /**
     * @param DocumentClassification $documentClassification
     *
     * @return self
     */
    public function setDocumentClassification($documentClassification)
    {
        $this->documentClassification = $documentClassification;

        return $this;
    }

    /**
     * @return DocumentClassification
     */
    public function getDocumentClassification()
    {
        return $this->documentClassification;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $externalCode
     *
     * @return self
     */
    public function setExternalCode($externalCode)
    {
        $this->externalCode = $externalCode;

        return $this;
    }

    /**
     * @return string
     */
    public function getExternalCode()
    {
        return $this->externalCode;
    }


    /**
     * @return GenericCollection|Pastoral\SubType\Model[]
     */
    public function getSubTypes(): GenericCollection
    {
        return $this->subTypes;
    }


    /**
     * @param Collection|Pastoral\SubType\Model[] $subTypes
     * @return $this
     * @throws DomainException
     */
    public function setSubTypes(Collection $subTypes): self
    {
        $this->subTypes->replaceWith($subTypes);
        return $this;
    }
}
