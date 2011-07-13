<?php

define('RSS_cache_time', 3000);

class XML {
    public $xml;

    function __construct ($url, $cache_time = 7200) {

		libxml_use_internal_errors(true);
		
        if(empty($url))
            return false;

        $cache_file = Cfg::get('xml_dir') . '/' . md5($url);
        //$cache_time = is_numeric($caching) ? $caching : 2*60*60;

        if(!file_exists($cache_file) || filectime($cache_file) < (time() - $cache_time))   {
            $xml_handle = @fopen($url, 'r');
            $cache_handle = @fopen($cache_file, 'w');

            if(!$xml_handle || !$cache_handle)
                return false;

            while(!feof($xml_handle)) {
                fwrite($cache_handle, fread($xml_handle, 8192), 8192);
            }

            fclose($xml_handle);
            fclose($cache_handle);
        }

        try {
            $this->xml = new SimpleXMLElement($cache_file, null, true);
        } catch (Exception $e) {
            unlink($cache_file);
            return false;
        }
    }

    function __get ($property) {
        if(isset($this->xml->$property)) {
            return $this->xml->$property;
        }
    }
}

?>