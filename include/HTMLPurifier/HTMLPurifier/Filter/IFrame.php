<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class HTMLPurifier_Filter_IFrame extends HTMLPurifier_Filter
{
    public $name = 'IFrame';

    /**
     *
     * @param string $html
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return string
     */
    public function preFilter($html, HTMLPurifier_Config $config, HTMLPurifier_Context $context)
    {
        $html = preg_replace('#<iframe#i', '<img class="MyIframe"', $html);
        $html = preg_replace('#</iframe>#i', '</img>', $html);
        return $html;
    }

    /**
     *
     * @param string $html
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return string
     */
    public function postFilter($html, HTMLPurifier_Config $config, HTMLPurifier_Context $context)
    {
        $post_regex = '#<img class="MyIframe"([^>]+?)>#';
        return preg_replace_callback($post_regex, array($this, 'postFilterCallback'), $html);
    }

    /**
     *
     * @param array $matches
     * @return string
     */
    protected function postFilterCallback($matches)
    {
        // Domain Whitelist
        $youTubeMatch = preg_match('#src="https?://www.youtube(-nocookie)?.com/#i', $matches[1]);
        $vimeoMatch = preg_match('#src="http://player.vimeo.com/#i', $matches[1]);
        $gMapsMatch = preg_match('#src="https?://maps.google.#i', $matches[1]);
        $gMaps2Match = preg_match('#src="https?://www.google.com(.[a-z]{2})?/maps#i', $matches[1]);
        if ($youTubeMatch || $vimeoMatch || $gMapsMatch || $gMaps2Match) {
            $extra = ' frameborder="0"';
            if ($youTubeMatch) {
                $extra .= ' allowfullscreen';
            } elseif ($vimeoMatch) {
                $extra .= ' webkitAllowFullScreen mozallowfullscreen allowFullScreen';
            } elseif ($gMapsMatch) {
                $extra .= 'allowFullScreen';
            }
            return '<iframe ' . $matches[1] . $extra . '></iframe>';
        } else {
            return '';
        }
    }

}
