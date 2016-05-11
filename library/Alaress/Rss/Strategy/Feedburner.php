<?php
/**
 * Alaress_Rss_Strategy_Feedbyrner
 *
 * Processes Feedburner feeds
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Rss_Strategy_Feedburner implements Alaress_Rss_Strategy_Interface {

	/**
	 * Get the title of the stream
	 *
	 * @param  $input
	 * @return
	 */
	public function getTitle($input) {
		return $input['title'];
	}

	/**
	 * Get the link of the stream
	 *
	 * @param  $input
	 * @return
	 */
	public function getLink($input) {
		return $input['link'];
	}

	/**
	 * Get a list of $limit RSS items
	 *
	 * Output should consist of an array containing
	 * title, link, description, publish date
	 *
	 * @param  $input
	 * @param  $limit
	 * @return array
	 */
	public function get($input, $limit) {
		$data = array();

		$allRss = $input['items'];

		/* Snap out at the limit. "All" is classified as zero
		 * in the homepage component.
		 */
		for ($i=0; ($i < count($allRss)); $i++) {
			if (($limit != 0) && ($i == $limit)) {
				break;
			}
			else {
				$currentRss = $allRss[$i];

				// Standardize pubDate into DateTime objects
				try {
					$pubDate = DateTime::createFromFormat(DateTime::RSS, $currentRss['pubDate']);
					if (!($pubDate instanceof DateTime)) {
						$pubDate = new DateTime($currentRss['pubDate']);
					}
				}
				catch (Exception $e) {
					$pubDate = null;
				}

				$data[] = array(
					'title' => $currentRss['title'],
					'link' => $currentRss['link'],
					'description' => (string)$currentRss['description'],
					'pubDate' => $pubDate,
				);
			}
		}

		return $data;
	}
}
