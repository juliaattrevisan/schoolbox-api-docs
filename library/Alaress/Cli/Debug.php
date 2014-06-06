<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Alaress\Cli;


class Debug {

	/** @var resource */
    protected $stream;

	/** @var string */
    protected $port = 'nill';

	/** @var array */
    protected $formats = array(
        'pid'  => '{%4s}',
        'mem'  => '[%6.2f / %6.2f]',
        'port' => '[%4d]',
        'time' => '[%s.%.8f]'
    );

	/** @var array */
    protected $visible = array();

	/**
	 * @param resource $stream
	 * @param array $fields
	 */
	public function __construct($stream, $fields = array('pid','time')) {
        $this->stream = $stream;
        $this->setVisibleFields($fields);
    }

	/**
	 * Verbose output
	 *
	 * @param string $message
	 * @param string $char
	 */
	public function v($message, $char = ' ') {
        if(!is_null($this->stream)) {

	        // if this is already formatted like a log, don't reformat it
	        if(preg_match("`\[.\].+ => `", $message)) {
		        fputs($this->stream, $message);
		        return;
	        }


	        try {
	            $c = '';

	            switch($char) {
	                case '!': $c = '1;31'; break;
	                case '?': $c = '0;33'; break;
	                case '+': $c = '0;32'; break;
	                case '-': $c = '0;34'; break;
	                case '*': $c = '0;31'; break;
	                case '>':
	                case '<': $c = '1;37'; break;
	            }

	            $str = array(
	                '[%s]' => $char
	            );

	            foreach($this->visible as $field) {
	                $str[$this->formats[$field]] = $this->getValueFor($field);
	            }

	            $format = sprintf("%s%s => %s%s\n",
	                $c ? "\033[{$c}m" : '',
	                implode(array_keys($str)),
	                $message,
	                $c ? "\033[0m" : ''
	            );

	            $args = array($format);

	            foreach($str as $val) {
	                if(is_array($val)) {
	                    foreach($val as $v) {
	                        $args[] = $v;
	                    }
	                }
	                else {
	                    $args[] = $val;
	                }
	            }

	            fputs($this->stream, call_user_func_array('sprintf', $args));
	        }
	        catch(\ErrorException $e) {
				// sprintf too few args
	        }
        }
    }

	/**
	 * Var dump
	 *
	 * @param mixed $data
	 */
	public function d($data) {
        ob_start();
        var_dump($data);
        $this->v(ob_get_contents(), 'd');
        ob_end_clean();
    }


	/**
	 * @param $field
	 * @return array|int|string
	 */
	protected function getValueFor($field) {
        switch($field) {
            case 'pid':  return getmypid();
            case 'mem':  return array(
                (int)(memory_get_usage(true))/(1024*1024),
                (int)(memory_get_peak_usage(true))/(1024*1024),
            );
            case 'port': return $this->port;
            case 'time':
                $time = microtime(true);
                $intt = (int)$time;
                $rmdr = $time - $intt;

                return array(
                    date("H:i:s",$intt),
                    $rmdr
                );
        }
        return '';
    }

	/**
	 * @param string $port
	 */
	public function setPort($port) {
        $this->port = $port;
    }

	/**
	 * @param array $fields
	 */
	public function setVisibleFields($fields) {
        // cast that sucker
        $valid = array_keys($this->formats);
        $this->visible = array();
        foreach((array)$fields as $f) {
            if(in_array($f, $valid)) {
                $this->visible[] = $f;
            }
        }
    }

} 