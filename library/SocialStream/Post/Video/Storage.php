<?php
/**
 * Youtube
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Video;

use Schoolbox\Mapper;
use Schoolbox\Resource\Storage as Store;
use Schoolbox\Video\ResourceVideo;
use Schoolbox\Video\ResourceVideoMapper;
use SocialStream\Post\Player\Player;

class Storage extends Player {

	/**
	 * Fetch thumbnail
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {
		$matches = array();

		preg_match('#^/cms/sendVideo\.php(.*)#', $this->getUrl(), $matches);

		/* Load via a script which generates a player with the chosen
		 * video in it, so you can embed local storage into anything
		 * via the usual iframe
		 */
		if (count($matches)) {

			/* Get the hash by snapping the query string into shiny little
			 * pieces, ripe for recombination
			 */
			$stuff = parse_url($matches[0]);
			parse_str($stuff['query'], $bits);

			// New style: hash is in $bits['id']
			// Old style: it's in $bits['hash']
			if (isset($bits['id'])) {
				$key  = 'id';
				$hash = $bits['id'];
			}
			else {
				$key  = 'hash';
				$hash = $bits['hash'];
			}

			// Figure out status of video
			$status = 'failed';
			try {
				/** @var Store\Model $storage */
				/** @var ResourceVideo      $video */
				$storage = Store\Mapper::getInstance()->findOneByColumn('hash', $hash);
				$video   = ResourceVideoMapper::getInstance()->findOneByColumnList(
					array(
						'hash' => $storage->getHash(),
						'type' => 'storage',
					)
				);
				$status = $video->getStatus();
			}
			catch (Mapper\Exception $e) {}
			return array(
				'poster' => "/cms/videoThumb.php?{$key}={$hash}&type=storage&size=poster",
				'src'    => "/cms/sendVideo.php?{$key}={$hash}&type=storage",
				'status' => $status,
			);
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->fetchThumbnail();
		}
	}
}