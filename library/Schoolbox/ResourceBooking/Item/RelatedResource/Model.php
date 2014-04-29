<?php
/**
 * Join model for resource relations.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\ResourceBooking\Item\RelatedResource;
use Schoolbox\ResourceBooking\Item\Model as Resource;

class Model {

    /**
     * @var int
     */
    protected $id;

    /**
     * @var Resource
     */
    protected $resource;

    /**
     * @var Resource
     */
    protected $relatedResource;

    /**
     * @param  int   $id
     * @return $this
     */
    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param  Resource $relatedResource
     * @return $this
     */
    public function setRelatedResource($relatedResource) {
        $this->relatedResource = $relatedResource;
        return $this;
    }

    /**
     * @return Resource
     */
    public function getRelatedResource() {
        return $this->relatedResource;
    }

    /**
     * @param  Resource $resource
     * @return $this
     */
    public function setResource($resource) {
        $this->resource = $resource;
        return $this;
    }

    /**
     * @return Resource
     */
    public function getResource() {
        return $this->resource;
    }

}
