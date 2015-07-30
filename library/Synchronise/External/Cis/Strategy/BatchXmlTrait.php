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
        $filename = Config::get('external_sync_isams_batch_xml');
        if (!$filename) {
            throw new Exception('external_sync_isams_batch_xml has not been configured');
        }
        if (!is_readable($filename)) {
            throw new Exception(sprintf('File "%s" does not exist', $filename));
        }

        $filemtime = filemtime($filename);
        if ($filemtime && ($_SERVER['REQUEST_TIME'] - $filemtime) > 48*60*60) {
            // File is more than 48 hours old: don't run sync again
            throw new Exception(
                sprintf(
                    'File "%s" was last modified more than 48 hours ago',
                    $filename
                )
            );
        }
        $reader = new XMLReader();
        $reader->open(Config::get('external_sync_isams_batch_xml'));
        return $reader;
    }
}
