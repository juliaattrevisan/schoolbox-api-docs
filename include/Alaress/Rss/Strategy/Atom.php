<?php
/**
 * Alaress_Rss_Strategy_Atom
 *
 * Processes ATOM feeds
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Rss_Strategy_Atom implements Alaress_Rss_Strategy_Interface {

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

		$allRss = $input->xml->entry;

		/* Snap out at the limit. "All" is classified as zero
		 * in the homepage component.
		 */
		for ($i=0; ($i < count($allRss)); $i++) {
			if (($limit != 0) && ($i == $limit)) {
				break;
			}
			else {
				$currentRss = $allRss[$i];

				$data[] = array(
					'title' => (string)$currentRss->title,
					'link' => (string)$currentRss->link[0]->attributes()->href,
					'description' => (string)$currentRss->summary,
					'pubDate' => (string)$currentRss->published,
				);
			}
		}

		return $data;
	}
}
