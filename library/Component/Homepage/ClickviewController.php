<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ClickviewController extends Component_Homepage_ControllerAbstract
{

    /**
     * Component name
     *
     * @var string
     */
    protected $componentName = 'ClickView';

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'clickview')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_clickview', 'has_clickview_block');
        $this->setBlock($templateHandle, 'online_clickview', 'online_clickview_block');
        $this->setBlock($templateHandle, 'iframe_clickview', 'iframe_clickview_block');
        $this->setBlock($templateHandle, 'select_video', 'select_video_block');


        $this->setVar('has_clickview_block', '');
        $this->setVar('online_clickview_block', '');
        $this->setVar('iframe_clickview_block', '');
        $this->setVar('CLICKVIEW_SCHOOL_ID', Config::get('clickview_school_id'));

        /* If no url is specified it might time out -_-
         * So just hide all this instead
         */
        if (strlen(trim($this->getConfig('url')))) {
            /* Pattern match the necessary code out of a url given to us
             * by the Clickview web interface
             *
             * http://webplayer.clickview.com.au/?p=kwRu&v1=home.clickview.com.au
             */
            $pattern = '/(\?|&)p=([a-zA-Z0-9#]+)/';

            /* New version of clickview has links in this format
            * http://online.clickview.com.au/MyLibrary/Play?Id=21b30fcc-e2f3-e28e-5ac9-6920d15d98b6&m=False
            */

            $patternOnline = '/(\?|&)Id=([a-zA-Z0-9\-]+)/';


            /* Latest version now provides iframe embedding but uses a masking URL, so annoying
            * http://clickv.ie/w/-eFa
             * Also can be embed code with the following URL
             * <iframe frameborder="0"
             *  allowfullscreen webkitallowfullscreen mozallowfullscreen
             *  width="640" height="360"
             *  src="http://online.clickview.com.au/Share/PlayEmbed?shareCode=b207b279&a=false"
             * ></iframe>
             */

            $patternRedir  = '/clickv\.ie\/w\/([a-zA-Z0-9\-]+)/';
            $patternIframe = '/(\?|&)shareCode=([a-zA-Z0-9\-]+)/i';


            $matches = array();

            if (preg_match($pattern, $this->getConfig('url'), $matches)) {
                $this->setVar('VIDEO_ID', $matches[2]);
                $this->parse('has_clickview_block', 'has_clickview');
            } elseif (preg_match($patternOnline, $this->getConfig('url'), $matches)) {
                $this->setVar('VIDEO_ID', $matches[2]);
                $this->parse('online_clickview_block', 'online_clickview');
            } elseif (preg_match($patternIframe, $this->getConfig('url'), $matches)) {
                $this->setVar('VIDEO_ID', $matches[2]);
                $this->parse('iframe_clickview_block', 'iframe_clickview');
            } elseif (preg_match($patternRedir, $this->getConfig('url'), $matches)) {
                //Fetch redirect headers from shorten URL for video ID
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->getConfig('url'));
                curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_exec($ch);
                $curlinfo = curl_getinfo($ch);

                parse_str(parse_url($curlinfo['redirect_url'], PHP_URL_QUERY), $parts);
                $parts = array_change_key_case($parts, CASE_LOWER);
                if (isset($parts['sharecode'])) {
                    // TODO: store the video ID once it is known
                    $this->setVar('VIDEO_ID', $parts['sharecode']);
                    $this->parse('iframe_clickview_block', 'iframe_clickview');
                }
            }
        }

        if ($this->permission >= WRITE_ACCESS) {
            $this->parse('select_video_block', 'select_video');
        } else {
            $this->setVar('select_video_block', '');
        }

        return true; // everything went according to plan
    }
}
