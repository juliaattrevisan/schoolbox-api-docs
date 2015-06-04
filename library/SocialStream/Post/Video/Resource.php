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
use Schoolbox\Video\ResourceVideo;
use Schoolbox\Video\ResourceVideoMapper;
use SocialStream\Post\Player\Player;

class Resource extends Player {

	/**
	 * Fetch thumbnail
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {
		$matches = array();

		preg_match('#^/send\.php(.*)#', $this->getUrl(), $matches);

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

			// Figure out status of video
			$status = 'failed';
			try {
				/** @var ResourceVideo $video */
				$video = ResourceVideoMapper::getInstance()->findByFileId($bits['id']);
				$status = $video->getStatus();
			}
			catch (Mapper\Exception $e) {}
			return array(
				'poster' => "/cms/videoThumb.php?id={$bits['id']}&size=poster",
				'src'    => "/cms/sendVideo.php?id={$bits['id']}",
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