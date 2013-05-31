<?php
/**
 * Alaress_Rss_Strategy_Rss20
 *
 * Processes RSS 2.0 feeds
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Rss_Strategy_Rss20 implements Alaress_Rss_Strategy_Interface {

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

		$allRss = $input->channel->item;

		/* Snap out at the limit. "All" is classified as zero
		 * in the homepage component.
		 */
		for ($i=0; ($i < count($allRss)); $i++) {
			if (($limit != 0) && ($i == $limit)) {
				break;
			}
			else {
				$currentRss = $allRss[$i];
				$content = $currentRss->children('http://purl.org/rss/1.0/modules/content/');
				$dc = $currentRss->children('http://purl.org/dc/elements/1.1/');

				$article = array(
					'title' => (string)$currentRss->title,
					'link' => (string)$currentRss->link,
					'description' => (string)$currentRss->description,
					'content'       => (string)$content->encoded,
					'pubDate' => (string)$currentRss->pubDate,
					'creator'   => (string)$dc->creator,
				);

				foreach ($dc->subject as $subject) {
                    $article['subject'][] = (string)$subject;
				}

				$data[] = $article;
			}
		}

		return $data;
	}


	/**
	 * Get the title of the stream
	 *
	 * @param  $input
	 * @return
	 */
	public function getTitle($input) {
		return $input->xml->channel->title;
	}

	/**
	 * Get the link of the stream
	 *
	 * @param  $input
	 * @return
	 */
	public function getLink($input) {
		return $input->xml->channel->link;
	}
}
