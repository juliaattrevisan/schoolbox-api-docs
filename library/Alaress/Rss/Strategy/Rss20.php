<?php

/**
 * Alaress_Rss_Strategy_Rss20
 *
 * Processes RSS 2.0 feeds
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Alaress_Rss_Strategy_Rss20 implements Alaress_Rss_Strategy_Interface
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

        /** @var SimpleXMLElement|null $allRss */
        $allRss = $input->channel->item ?? null;

        /* the Channel and Item nodes don't exist in this xml
         * Nothing to parse and show; exit early
         */
        if (null === $allRss) {
            return $data;
        }

        /* Snap out at the limit. "All" is classified as zero
         * in the homepage component.
         */
        for ($i = 0; ($i < count($allRss)); $i++) {
            if (($limit !== 0) && ($i === $limit)) {
                break;
            }

            $currentRss = $allRss[$i];
            $content = $currentRss->children('http://purl.org/rss/1.0/modules/content/');
            $dc = $currentRss->children('http://purl.org/dc/elements/1.1/');

            // Standardize pubDate into DateTime objects
            try {
                $pubDate = DateTime::createFromFormat(DateTime::RSS, $currentRss->pubDate);
                if (!($pubDate instanceof DateTime)) {
                    $pubDate = new DateTime($currentRss->pubDate);
                }
            } catch (Exception $e) {
                $pubDate = null;
            }

            $article = array(
                'title'       => (string)$currentRss->title,
                'link'        => (string)$currentRss->link,
                'description' => (string)$currentRss->description,
                'content'     => (string)$content->encoded,
                'pubDate'     => $pubDate,
                'creator'     => (string)$dc->creator,
            );

            foreach ($dc->subject as $subject) {
                $article['subject'][] = (string)$subject;
            }

            $data[] = $article;
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
        return $input->xml->channel->title ?? '';
    }

    /**
     * Get the link of the stream
     *
     * @param Xml $input
     * @return string
     */
    public function getLink(Xml $input): string
    {
        return $input->xml->channel->link ?? '';
    }
}
