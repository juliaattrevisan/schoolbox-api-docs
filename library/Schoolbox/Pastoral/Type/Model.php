<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Type;

use Schoolbox\GenericCollection;
use Schoolbox\Pastoral;
use DomainException;
use Schoolbox\Pastoral\DocumentClassification\Model as DocumentClassification;

class Model extends TypeAbstract
{
    /** @var GenericCollection|Pastoral\SubType\Model[] */
    private GenericCollection $subTypes;

    /**
     * @var DocumentClassification|null
     */
    protected DocumentClassification | null $documentClassification = null;
    protected DocumentClassification | null $documentClassificationConfidential = null;

    public function __construct()
    {
        parent::__construct();
        $this->subTypes = new GenericCollection(Pastoral\SubType\Model::class);
    }

    /**
     * @return GenericCollection|Pastoral\SubType\Model[]
     */
    public function getSubTypes(): GenericCollection
    {
        return $this->subTypes;
    }

    /**
     * @param GenericCollection|Pastoral\SubType\Model[] $subTypes
     * @return self
     * @throws DomainException
     */
    public function setSubTypes(GenericCollection $subTypes): self
    {
        $this->subTypes->replaceWith($subTypes);
        return $this;
    }

    /**
     * @param DocumentClassification|null $documentClassification
     *
     * @return self
     */
    public function setDocumentClassification(DocumentClassification | null $documentClassification): self
    {
        $this->documentClassification = $documentClassification;
        return $this;
    }

    /**
     * @return DocumentClassification|null
     */
    public function getDocumentClassification(): DocumentClassification | null
    {
        return $this->documentClassification;
    }

    /**
     * @return DocumentClassification|null
     */
    public function getDocumentClassificationConfidential(): DocumentClassification | null
    {
        return $this->documentClassificationConfidential;
    }

    /**
     * @param DocumentClassification|null $documentClassification
     *
     * @return self
     */
    public function setDocumentClassificationConfidential(DocumentClassification | null $documentClassification): self
    {
        $this->documentClassificationConfidential = $documentClassification;
        return $this;
    }
}
