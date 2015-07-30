<?php
/**
 * A trait for the external database - defines how the XML reader is created.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

trait Synchronise_External_Cis_Strategy_BatchXmlTrait
{
    /**
     * Gets the XML reader for this sync.
     *
     * @return XMLReader
     * @throws Exception if the file doesn't exist, or it's too old (more than 48 hours old)
     */
    protected function getXmlReader()
    {
        $dir = Config::get('stream_dir');
        if (!$dir) {
            throw new Exception('stream_dir has not been configured');
        }

        $filename = $dir.strftime('/isams_batch-%Y%m%d.xml', $_SERVER['REQUEST_TIME']);
        printf("Using file \"%s\"\n", $filename);
        if (!file_exists($filename)) {
            printf("File \"%s\" doesn't exist - downloading...\n", $filename);
            // Download this file from their API
            $host = Config::get('external_sync_isams_batch_api_host');
            $key  = Config::get('external_sync_isams_batch_api_key');
            if (!$host) {
                throw new Exception('isams_batch_api_host has not been configured');
            }
            if (!$key) {
                throw new Exception('isams_batch_api_key has not been configured');
            }
            $api = new External_Isams_Batch_BaseApi($host, $key);
            file_put_contents($filename, $api->retrieveDocument());
        }
        $reader = new XMLReader();
        $reader->open($filename);
        return $reader;
    }
}
