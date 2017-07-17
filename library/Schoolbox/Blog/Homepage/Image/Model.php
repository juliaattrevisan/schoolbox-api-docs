<?php
/**
 * Model for normal (non-eportfolio) images
 * 
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog\Homepage\Image;

use \Schoolbox\Mapper\Traits\Model\Id;
use \Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use \Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use \Schoolbox\Mapper\Traits\Model\DeletedAtDate;

class Model {

    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

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

	/**
	 * @param string $hash
	 * @return self
	 */
	public function setHash($hash) {
		$this->hash = $hash;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getHash() {
		return $this->hash;
	}
} 