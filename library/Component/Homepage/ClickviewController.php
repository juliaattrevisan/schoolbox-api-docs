<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use \Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_ClickviewController extends Component_Homepage_ControllerAbstract
{
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

        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
        $this->setBlock($templateHandle, 'has_clickview', 'has_clickview_block');
        $this->setBlock($templateHandle, 'online_clickview', 'online_clickview_block');
        $this->setBlock($templateHandle, 'iframe_clickview', 'iframe_clickview_block');

        $clickviewSchoolId = Config::get('clickview_school_id');
        $this->setVar('CLICKVIEW_SCHOOL_ID', $clickviewSchoolId);

        // Clickview has different endpoints in NZ to Aus.  No idea if this affects any other countries.
        if (stripos($this->getConfigLoader()->get('date_timezone'), 'Auckland') !== false) {
            $this->setVar('CLICKVIEW_URL', 'online.clickview.co.nz');
        } else {
            $this->setVar('CLICKVIEW_URL', 'online.clickview.com.au');
        }

        $showEmpty = false;
        $emptyInvalid = false;

        if (!strlen(trim($this->getConfig('url')))) {
            $showEmpty = true;
        } else {

            $this->setVar([
                'empty_state_block' => '',
                'has_clickview_block' => '',
                'online_clickview_block' => '',
                'iframe_clickview_block' => '',
                'HIDE_CLICKVIEW' => '',
            ]);

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

            $patternRedir = '/clickv\.ie\/w\/[a-zA-Z0-9\-]+(?:(?:\?t=([0-9]+))?(?:\&et=([0-9]+))?)?/';
            $patternIframe = '/(\?|&)shareCode=([a-zA-Z0-9\-]+)/i';

            $matches = array();

            $this->setVar([
                'START' => '',
                'END' => '',
            ]);

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
                // Is there a start and end?
                // https://clickv.ie/w/idGe?t=305&et=408&i=2
                // https://clickv.ie/w/idGe

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
                    $this->setVar('VIDEO_ID', $parts['sharecode']);
                    // Pass in start/end time parameters
                    if (count($matches) == 3) {
                        $this->setVar('START', "&t={$matches[1]}");
                        $this->setVar('END', "&et={$matches[2]}");
                    }
                    $this->parse('iframe_clickview_block', 'iframe_clickview');
                }
            } else {
                $showEmpty = true;
                $emptyInvalid = true;
            }

        }

        if ($showEmpty) {

            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_config', 'empty_config_block');
            $this->setBlock('empty_state', 'empty_invalid', 'empty_invalid_block');

            $this->setVar([
                'has_clickview' => '',
                'online_clickview' => '',
                'iframe_clickview' => '',
                'empty_read_block' => '',
                'empty_config_block' => '',
                'empty_invalid_block' => '',
                'HIDE_CLICKVIEW' => 'hide',
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_block', 'empty_read');
            } else {
                if ($emptyInvalid) {
                    $this->parse('empty_invalid_block', 'empty_invalid');
                } else {
                    $this->parse('empty_config_block', 'empty_config');
                }
            }

            $this->parse('empty_state_block', 'empty_state');
        }

        return true; // everything went according to plan
    }


}
