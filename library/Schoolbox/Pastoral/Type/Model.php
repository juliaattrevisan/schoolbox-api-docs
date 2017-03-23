<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Type;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Pastoral\DocumentClassification\Model as DocumentClassification;

class Model implements ISoftDeletable {

    use Id;
    use DeletedAtDate;

    /** @var string */
    protected $name;

    /** @var DocumentClassification */
    protected $documentClassification;

    /** @var string */
    protected $externalCode;

    /**
     * @param DocumentClassification $documentClassification
     * @return self
     */
    public function setDocumentClassification($documentClassification) {
        $this->documentClassification = $documentClassification;
        return $this;
    }

    /**
     * @return DocumentClassification
     */
    public function getDocumentClassification() {
        return $this->documentClassification;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $externalCode
     * @return self
     */
    public function setExternalCode($externalCode) {
        $this->externalCode = $externalCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getExternalCode() {
        return $this->externalCode;
    }
}