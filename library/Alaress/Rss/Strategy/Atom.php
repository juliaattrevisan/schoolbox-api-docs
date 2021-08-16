<?php

/**
 * Alaress_Rss_Strategy_Atom
 *
 * Processes ATOM feeds
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class Alaress_Rss_Strategy_Atom implements Alaress_Rss_Strategy_Interface
{

    /**
     * Get a list of $limit RSS items
     *
     * Output should consist of an array containing
     * title, link, description, publish date
     *
     * @param Xml $input
     * @param int $limit
     * @return array
     */
    public function get(Xml $input, int $limit): array
    {
        $data = [];

        $allRss = $input->xml->entry;

        /* the Xml and Entry nodes don't exist in this xml
         * Nothing to parse and show; exit early
         */
        if (null === $allRss) {
            return $data;
        }

        /* Snap out at the limit. "All" is classified as zero
         * in the homepage component.
         */
        for ($i = 0; ($i < count($allRss)); $i++) {
            if (($limit != 0) && ($i == $limit)) {
                break;
            }

            $currentRss = $allRss[$i];

            // Standardize pubDate into DateTime objects (or null)
            try {
                $pubDate = DateTime::createFromFormat(DateTime::RSS, $currentRss->published);
                if (!($pubDate instanceof DateTime)) {
                    $pubDate = new DateTime($currentRss->published);
                }
            } catch (Exception $e) {
                $pubDate = null;
            }

            $data[] = array(
                'title'       => (string)$currentRss->title,
                'link'        => (string)$currentRss->link[0]->attributes()->href,
                'description' => (string)$currentRss->summary,
                'pubDate'     => $pubDate,
            );
        }

        return $data;
    }

    /**
     * Get the title of the stream
     *
     * @param Xml $input
     * @return string
     */
    public function getTitle(Xml $input): string
    {
        return $input->xml->title;
    }

    /**
     * Get the link of the stream
     *
     * @param  Xml $input
     * @return string
     */
    public function getLink(Xml $input): string
    {
        return $input->xml->link[0]->attributes()->href;
    }
}
