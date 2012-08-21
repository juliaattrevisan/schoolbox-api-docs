<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Debug {

    const NONE   = -1;
    const OUTPUT = 0;
    const EMAIL  = 1;
    const FILE   = 2;

    private static $output = array();
    private static $defaultEmail = 'matt.day@alaress.com.au';
    private static $inhibit = false;

    // this class does not get instantiated
    protected function __construct() { }

    public static function add($text) {
        self::$output[] = $text;
    }

    public static function output($method = Debug::OUTPUT, $flush = true, $email = '') {
return;
        if(self::$inhibit || !count(self::$output))
            return;

        // how do I handle myself?
        switch($method) {
            default:
            case Debug::OUTPUT:
                // display_errors should correlate to 'development' vs 'production'
                $disp = 'style="display:' . (ini_get('display_errors') ? 'block' : 'none') .'"';
                $implode = implode("\n", self::$output);
                echo
                '
<style type="text/css">
#dbg {
    opacity: 0.5;
    font-size: 10px;
    line-height: 2.5em;
    z-index: 999999;
    position: fixed;
    background: #fff;
    right: 0;
    top: 0;
    height: 100%;
    width: 20%;
    min-width: 200px;
    border-left: solid 1px #333;
    padding-left: 5px;
}
#dbg a {
    display: block;
    width: 105%;
    text-align: center;
    background: #0066B0;
    color: #fff;
    text-decoration: none;
    margin-left: -5px;
    font-weight: bold;
}
</style>
';
                echo "<pre id=\"dbg\" $disp><a onclick=\"document.getElementById('dbg').style.display='none';\">X</a><br/>$implode</pre>";
                break;

            case Debug::EMAIL:
                $email = $email ? $email : self::$defaultEmail;
                $body = '--- ' . date('Y-m-d H:i:s') . "\n" . implode('\n', self::$output) . ' ---';
                mail($email, 'Giza Debug', $body);
                break;

            case Debug::FILE:
                $file = fopen('/tmp/giza_debug' . date('YmdHis'), 'w');
                foreach(self::$output as $o) {
                    fputs($file, $o, strlen($o));
                }
                fclose($file);
                break;
        }

        if($flush) {
            self::$output = array();
        }
    }

    public static function inhibit() {
        self::$inhibit = true;
    }

    public static function release() {
        self::$inhibit = false;
    }
}
