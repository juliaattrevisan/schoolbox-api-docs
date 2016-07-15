<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Message;


use Schoolbox\Mapper;

class NSMapper extends Mapper {

	const NS_PASTORAL         		= 'pastoral';
	const NS_PASTORAL_COMMENT 		= 'pastoral_comment';
	const NS_PASTORAL_LOG     		= 'pastoral_log';
	const NS_DUEWORK          		= 'duework';
	const NS_ONLINE_ASSESS    		= 'assess';
	const NS_BLOG             		= 'blog';
	const NS_FORUM            		= 'forum';
	const NS_NEWS             		= 'news';
	const NS_CALENDAR         		= 'calendar';
	const NS_BOARDER_LEAVE   		= 'leave';
	const NS_WIKI             		= 'wiki';
	const NS_GROUP            		= 'group';
	const NS_CMS              		= 'cms';
	const NS_SSTREAM          		= 'sstream';
	const NS_EOTC             		= 'eotc';
	const NS_RESOURCE_BOOKING 		= 'resource_booking';
	const NS_REPORT_CARD      		= 'report_card';
	const NS_SPORTS_FIXTURES  		= 'sports_fixtures';
	const NS_SPORTS_FIXTURES_RESULT = 'sports_fixtures_result';


	protected function mapping() {
		$this->hasTable('msg_namespace');
		$this->hasModel('\\Schoolbox\\Realtime\\Message\\NS');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'slug',
			'property'   => 'slug',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'label',
			'property'   => 'label',
			'type'       => self::TYPE_STRING,
		));
	}

	/**
	 * @param string $slug
	 * @return NS
	 */
	public function findOneBySlug($slug) {
        try {
            return $this->findOneByColumn('slug', $slug);
        }
        catch(Mapper\Exception $e) {
            return null;
        }
	}

}