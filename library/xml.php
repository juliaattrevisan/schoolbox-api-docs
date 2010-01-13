<?php

require_once($_SERVER['DOCUMENT_ROOT'] .'/conf/conf.php');

class XML {
    public $xml;

    function __construct ($url, $cache_time = 7200) {
        $cache_file = CACHE_xml . '/' . md5($url);
        //$cache_time = is_numeric($caching) ? $caching : 2*60*60;

        if(!file_exists($cache_file) || filectime($cache_file) > (time() + $cache_time))   {
            file_put_contents($cache_file, file_get_contents($url));
        }

        try {
            $this->xml = new SimpleXMLElement($cache_file, null, true);
        } catch (Exception $e) {
            unlink($cache_file);
        }
    }

    function __get ($property) {
        if(isset($this->xml->$property)) {
            return $this->xml->$property;
        }
    }
}

?>