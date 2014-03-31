<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Schoolbox\Mail;


use Alaress\Mail\TwigMailer;
use Schoolbox\UserMapper;

class TwigEmail extends TwigMailer {

    /** @var array */
    protected $coreContext;

    /** @var UserMapper */
    protected $userMapper;


    /**
     * Prepares the email by importing various configurations from the system
     *
     * @param string $template - filename of the Twig template, relative to 'templates/[core|site]/email'
     * @param string $subject  - the default email subject, can be changed after construction
     * @param string $to       - the defailt recipient, can be changed after construction
     * @param string $host     - deprecated, does nothing
     */
    public function __construct($template, $subject = "", $to = "", $host = "") {
        parent::__construct($template, $subject, $to, $host);
        $this->userMapper = UserMapper::getInstance();

        // config context setup

        $neededConfigs = array(
            'title',
            'school_name',
            'site_hostname',
            'site_location',
            'site_domain',
        );

        foreach($neededConfigs as $key) {
            $this->coreContext['config'][$key] = self::getConfig()->get($key);
        }

        $this->coreContext['config']['year'] = date('Y');
        $this->coreContext['config']['padding'] = 4;

        $site = $this->coreContext['config']['site_location'];

        $possibleLogos = array(
            'email-logo',
            "$site-logo",
            'hercules',
            'logo'
        );

        foreach($possibleLogos as $filename) {
            if(file_exists(SITE_root . "/www/images/$site/$filename.png")) {
                $this->coreContext['config']['logo'] = $filename;
                break;
            }
        }

        if(!isset($this->coreContext['config']['logo'])) {
            $this->coreContext['config']['logo'] = 'logo';
        }


        // extract colours
        $colours = $this->parseLess(SITE_root . "/www/css/core/variables.less");
        $colours = $this->parseLess(SITE_root . "/www/css/$site/chrome.less", $colours);
        $this->coreContext['colours'] = $colours;
    }


    /**
     * Accepts the filename of a LESS file and parses it for colour variables
     *
     * @param string $filename - the name of the LESS file
     * @param array $colours   - a previous colour definition with which to merge the parsing of the given file
     * @return array           - a named array of colours
     */
    protected function parseLess($filename, $colours = array()) {

        $fp = @fopen($filename, 'r');
        if($fp === false) {
            return $colours;
        }
        // iterate and extract colour vars
        while($line = fgets($fp)) {

            $regex = '`
				@                       # vars start with an at
				([a-z\-]+)              # get the varname
				:                       # delimited by a colon
				\s+                     # miscellaneous amounts of whitespace
				(?:                     # $1
					(\#[0-9A-Fa-f]+)    # colour declaration
				|                       # OR
					@([a-z\-]+)         # alternative var reference
				)                       # /$1
				;                       # trailing semicolon delimiter
			`x';

            if(!preg_match($regex, $line, $matches)) {
                continue;
            }

            $matches[1] = str_replace('-','_', $matches[1]);

            if(count($matches) == 3) {
                $colours[$matches[1]] = strtoupper($matches[2]);
            }
            elseif(count($matches) == 4) {
                $matches[3] = str_replace('-','_', $matches[3]);
                if(isset($colours[$matches[3]])) {
                    $colours[$matches[1]] = $colours[$matches[3]];
                }
                else {
                    $colours[$matches[1]] = '{'.$matches[3].'}';
                }
            }

        }
        fclose($fp);
        return $colours;
    }


    /**
     * Use both $coreContext and $context to compose HTML
     *
     * @return string
     */
    protected function composeHTML() {
        return $this->rendercache = $this->twig->render($this->templateFilename,
            array_merge_recursive($this->coreContext, $this->context)
        );
    }

    public function getCoreContext() {
        return $this->coreContext;
    }

    public function setSubject($subject) {
        parent::setSubject($subject);
        $this->coreContext['config']['subject'] = $subject;
    }


}