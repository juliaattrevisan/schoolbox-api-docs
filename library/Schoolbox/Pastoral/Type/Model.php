<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Type;

use Schoolbox\GenericCollection;
use Schoolbox\Pastoral;
use DomainException;
use Schoolbox\Pastoral\DocumentClassification\Model as DocumentClassification;

class Model extends TypeAbstract
{
    /** @var GenericCollection|Pastoral\SubType\Model[] */
    private GenericCollection $subTypes;

    /** @var DocumentClassification|int|null */
    protected int | DocumentClassification | null $documentClassification = null;

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
     * @param int|DocumentClassification|null $documentClassification
     *
     * @return self
     */
    public function setDocumentClassification(DocumentClassification | int | null $documentClassification): self
    {
        $this->documentClassification = $documentClassification;
        return $this;
    }

    /**
     * @return DocumentClassification|int|null
     */
    public function getDocumentClassification(): DocumentClassification | int | null
    {
        return $this->documentClassification;
    }
}
