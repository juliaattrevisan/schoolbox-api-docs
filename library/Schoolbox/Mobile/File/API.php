<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class File_API extends Generic_API {

    public function View($fileID, $save = false) {

        $fh = new File();
        $file = $fh->getByID($fileID);

        if(!$file) {
            return new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
        }


        $gh = new Group();
        if($gh->getPermission($file['category']) < READ_ACCESS) {
            return new Response_HTTP(Response_HTTP::HTTP_FORBIDDEN);
        }

        $asAttachment = $save ? 'attachment; ' : '';

        $filename = Config::get('file_dir') ."/$fileID.dat";

        $filesize = @filesize($filename);
        if($filesize === false) {
            return new Response_HTTP(Response_HTTP::HTTP_SERVER_ERROR);
        }

        $response = new Response_HTTP();

        // if not using iDevice
        //if($_SESSION['env']['device'] != ClientDevice::DEV_IPAD && $_SESSION['env']['device'] != ClientDevice::DEV_IPHONE) {
            $response->addHeader('Content-Disposition', $asAttachment . "filename=\"{$file['realname']}\"");
            $response->addHeader('Content-Length', $filesize);
            $response->addHeader('Pragma', '');
            $response->addHeader('Cache-Control', '');
        //}
        // if yes using iDevice
        //else {
        //    $response->addHeader('Pragma', 'public');
        //    $response->addHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0, private');
        //}

        $response->addHeader('Content-Type', $file['mime_type']);

        ob_start();
        readfile($filename);

        $response->setResponse('filedata', ob_get_contents());
        $response->setResponse('articles', array(array(
            'title' => 'Downloading...'
        )));

        ob_end_clean();

        $response->setPage('type', 'file');

        return $response;

    }

}
