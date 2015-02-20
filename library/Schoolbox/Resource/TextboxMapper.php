<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;

use Schoolbox\User;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Resource\Textbox\Search\Result;

class TextboxMapper extends Mapper implements Searchable {

	protected function mapping() {
		$this->hasTable('textBox');
		$this->hasModel('\\Schoolbox\\Resource\\Textbox');

		$this->hasMap(array(
			'column'     => 'textBoxID',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'categoryID',
			'property'   => 'category',
			'type'       => self::TYPE_MODEL,
			'join'       => 'category'
		));

		$this->hasMap(array(
			'column'     => 'heading',
			'property'   => 'heading',
			'type'       => self::TYPE_STRING
		));

		$this->hasMap(array(
			'column'     => 'body',
			'property'   => 'body',
			'type'       => self::TYPE_STRING
		));

		$this->hasMap(array(
			'column'     => 'deleted',
			'property'   => 'deleted',
			'type'       => self::TYPE_BOOL_ENUM
		));

		$this->hasJoin('category', array(
			'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
			'this_key'  => 'categoryID',
			'other_key' => 'id'
		));

        $this->hasJoin(
            'perms',
            array(
                'mapper' => '\\Schoolbox\\Permission\\Access\\RoleAccessMapper',
                'this_key' => 'categoryID',
                'other_key' => 'cid'
            )
        );

        $this->hasJoin(
            'userAccess',
            array(
                'mapper' => '\\Schoolbox\\Permission\\Access\\UserAccessMapper',
                'this_key' => 'categoryID',
                'other_key' => 'sid'
            )
        );

        /* Took one on the chin for samuel
         */
		$this->hasSoftDeleteField('deleted', 'true', 'false');
	}

    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * This also returns a count of all the items found in the search, not limited
     * by params
     *
     * @param User $user
     * @param string $keyword
     * @param int $offset
     * @param int $limit
     * @param string $sortProperty
     * @param string $sortDirection
     * @return Collection
     */
    public function search($user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT, $sortProperty = '', $sortDirection = 'DESC')
    {
        if ($limit == 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $searcher = new Result();
        $searcher->search($user, $this, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        return $searcher;
    }
}