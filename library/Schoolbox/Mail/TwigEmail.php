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
use FM\CSSIN;
use Schoolbox\UserMapper;

class TwigEmail extends TwigMailer {

    /** @var array */
    protected $coreContext;

    /** @var UserMapper */
    protected $userMapper;

	/** @var CSSIN */
	protected $cssIn;

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
	    $this->cssIn = new CSSIN();

        // config context setup

        $neededConfigs = array(
            'title',
            'school_name',
	        'school_address',
            'site_hostname',
            'site_location',
            'site_domain',
        );

        foreach($neededConfigs as $key) {
            $this->coreContext['config'][$key] = self::getConfig()->get($key);
        }

	    $this->coreContext['config']['fqdn'] = trim(
	        $this->coreContext['config']['site_hostname'].".".$this->coreContext['config']['site_domain']
	    ,'.');

        $this->coreContext['config']['year'] = date('Y');
        $this->coreContext['config']['padding'] = 4;

        $site = $this->coreContext['config']['site_location'];

        $possibleLogos = array(
            'email-logo',
            "$site-logo",
	        'logo',
            'hercules',
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
        $this->coreContext['colours'] = $this->compileLess(array(
	        SITE_root . "/www/css/core/variables.less",
	        SITE_root . "/www/css/$site/chrome.less"
        ));
    }


	/**
	 * Accepts names of LESS files and parses them as a group for colour variables
	 *
	 * @param array $sources
	 * @return array
	 */
	protected function compileLess($sources) {

		// build list (which leaves references intact
		$colours = array();
		foreach($sources as $file) {
			$colours = $this->parseLess($file, $colours);
		}

		$unresolved = array();

		// collate references
		foreach($colours as $key => $value) {
			if($value[0] == '@') {
				$ref = substr($value, 1);
				$unresolved[$key] = $ref;
			}
		}

		// resolve
		while(count($unresolved)) {
			foreach($unresolved as $key => $true) {
				$ref = substr($colours[$key], 1);
				if(isset($colours[$ref])) {
					$colours[$key] = $colours[$ref];
					if($colours[$key][0] != '@') {
						unset($unresolved[$key]);
					}
				}
				else {
					$colours[$key] = '';
					unset($unresolved[$key]);
				}
			}
		}

		return $colours;
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
				@                                                                            # vars start with an at
				([a-z\-]+)                                                                   # get the varname
				:                                                                            # delimited by a colon
				\s+                                                                          # miscellaneous amounts of whitespace
				(?:                                                                          # $1
					(\#[0-9A-Fa-f]+|rgba?\([0-9]{1,3},[0-9]{1,3},[0-9]{1,3}(?:,[0-9\.])\)?)  # colour declaration
				|                                                                            # OR
					(@[a-z\-]+)                                                              # alternative var reference
				)                                                                            # /$1
				;                                                                            # trailing semicolon delimiter
			`x';

            if(!preg_match($regex, $line, $matches)) {
                continue;
            }

	        // @varname: drop hyphens to underscores for compatibility
            $matches[1] = str_replace('-','_', $matches[1]);

	        // if 3 matches, is a colour declaration
            if(count($matches) == 3) {
                $colours[$matches[1]] = strtolower($matches[2]);
            }
            // if 4 matches, is a reference
            elseif(count($matches) == 4) {
                $colours[$matches[1]] = str_replace('-','_', $matches[3]);
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
    public function composeHTML() {
        $preformat = $this->twig->render($this->templateFilename,
            array_replace_recursive($this->coreContext, $this->context)
        );
	    $this->rendercache = $this->cssIn->inlineCSS('', $preformat);
	    // truncate all sequential whitespace characters
	    $this->rendercache = preg_replace('`\s\s*`', ' ', $this->rendercache);
	    return $this->rendercache;
    }


	/**
	 * Return the configuration elements of config for this email
	 *
	 * @return array
	 */
	public function getCoreContext() {
        return $this->coreContext;
    }


	/**
	 * Set the subject, both in the email header and the config
	 *
	 * @param $subject
	 */
	public function setSubject($subject) {
        parent::setSubject($subject);
        $this->coreContext['config']['subject'] = $subject;
    }

}