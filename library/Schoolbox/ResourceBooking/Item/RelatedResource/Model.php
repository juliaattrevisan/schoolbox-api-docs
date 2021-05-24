<?php

/**
 * Join model for resource relations.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Item\RelatedResource;

use Schoolbox\ResourceBooking\Item\Model as ResourceModel;

class Model
{

    /**
     * @var int
     */
    protected $id;

    /**
     * @var ResourceModel
     */
    protected $resource;

    /**
     * @var ResourceModel
     */
    protected $relatedResource;

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param ResourceModel $relatedResource
     * @return $this
     */
    public function setRelatedResource($relatedResource)
    {
        $this->relatedResource = $relatedResource;
        return $this;
    }

    /**
     * @return ResourceModel
     */
    public function getRelatedResource()
    {
        return $this->relatedResource;
    }

    /**
     * @param ResourceModel $resource
     * @return $this
     */
    public function setResource($resource)
    {
        $this->resource = $resource;
        return $this;
    }

    /**
     * @return ResourceModel
     */
    public function getResource()
    {
        return $this->resource;
    }
}
