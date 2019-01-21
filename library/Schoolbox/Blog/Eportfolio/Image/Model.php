<?php
/**
 * Model for e-portfolio images
 * 
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog\Eportfolio\Image;

use Schoolbox\ISoftDeletable;
use \Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use \Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use \Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\Resource\Storage\IStorage;

class Model implements IStorage, ISoftDeletable {

    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    use StorableTrait;

	/** @var int */
	protected $blogId;

	/** @var string */
	protected $hash;

	/**
	 * @param int $blogId
	 * @return self
	 */
	public function setBlogId($blogId) {
		$this->blogId = $blogId;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getBlogId() {
		return $this->blogId;
	}
}
