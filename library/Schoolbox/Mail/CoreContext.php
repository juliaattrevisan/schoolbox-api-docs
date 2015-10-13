<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mail;


use Schoolbox\IConfigLoader;

class CoreContext {

    /** @var array */
    protected $context;

    /**
     * @param IConfigLoader $conf
     */
    public function __construct(IConfigLoader $conf)
    {
        $required = array(
            'title',
            'school_name',
            'school_address',
            'site_hostname',
            'site_location',
            'site_domain',
            'skin_builder',
        );

        foreach($required as $key) {
            $this->context['config'][$key] = $conf->get($key);
        }

        $this->context['config']['fqdn'] = trim(
            $this->context['config']['site_hostname'].".".$this->context['config']['site_domain'],
            '.'
        );

        $this->context['config']['year'] = date('Y');
        $this->context['config']['padding'] = 4;

        $site = $this->context['config']['site_location'];

        $possibleLogos = array(
            'email-logo',
            "$site-logo",
            'logo',
            'hercules',
        );

        foreach($possibleLogos as $filename) {
            if(file_exists(SITE_root . "/www/images/$site/$filename.png")) {
                $this->context['config']['logo'] = $filename;
                break;
            }
        }

        if(!isset($this->context['config']['logo'])) {
            $this->context['config']['logo'] = 'logo';
        }


        // extract colours
        $this->context['colours'] = $this->compileLess(array(
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

    public function getContext()
    {
        return $this->context;
    }

}