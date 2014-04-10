<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mapper;


use Schoolbox\Collection;
use Schoolbox\User;

interface Searchable {

	const DEFAULT_LIMIT = 20;

	/**
	 * Performs a search with the given parameters, and returns a Collection of the resultant set.
	 *
	 * @param User $user
	 * @param string $keyword
	 * @param int $offset
	 * @param int $limit
	 * @param string $sortProperty
	 * @param string $sortDirection
	 * @return Collection
	 */
	public function search($user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT, $sortProperty = '', $sortDirection = 'DESC');

} 