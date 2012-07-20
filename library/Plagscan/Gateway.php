<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class Plagscan_Gateway {
    const API_VERSION = '2.0';

    const API_URL = 'https://www.plagscan.com/api/';

    const METHOD_SUBMIT = 'Submit';

    const METHOD_RETRIEVE = 'Retrieve';

    const METHOD_CHECK = 'Check';

    private $username;

    private $apiKey;

    private $errorCode;

    private $errorMessage;

    private $response;

    public function __construct ($username, $apiKey) {
        $this->username = $username;
        $this->apiKey = $apiKey;
    }

    public function getErrorCode () {
        return $this->errorCode;
    }

    public function getErrorMessage () {
        return $this->errorMessage;
    }

    public function submit ($documentName, $documentFile) {
        $parameterList = $this->createParameters(array(
            'METHOD' => self::METHOD_SUBMIT
        ));

        $documentParameter = '';

        $fileInfo = new finfo(FILEINFO_MIME);

        $documentData = file_get_contents($documentFile);
        $documentType = $fileInfo->buffer($documentData);

        $documentExtension = strtolower( trim( array_pop( explode('.', $documentName) ) ) );

        $validDocumentExtensionList = array(
            'docx',
            'doc',
            'pdf',
            'txt',
            'rtf',
            'html',
        );

        if ( !in_array($documentExtension, $validDocumentExtensionList) ) {
            return false;
        }

        $documentParameter .= "Content-Disposition: form-data; name=\"DATA\"; filename=\"$documentName\"\n";
        $documentParameter .= "Content-Type: $documentType\n";
        $documentParameter .= "Content-Transfer-Encoding: binary\n\n";
        $documentParameter .= "$documentData\n";

        $this->createRequest($parameterList, array($documentParameter));

        $response = $this->parseXmlResponse($this->response);

        return $response->PID;
    }

    public function retrieve ($documentId, $retrieveMode) {
        $parameterList = $this->createParameters(array(
            'METHOD' => self::METHOD_RETRIEVE,
            'PID'    => $documentId,
            'MODE'   => $retrieveMode
        ));

        if ( $this->createRequest($parameterList) ) {
            $response = $this->parseXmlResponse($this->response);

            return $response;
        }
        else {
            return false;
        }
    }

    public function check ($documentId) {
        $parameterList = $this->createParameters(array(
            'METHOD' => self::METHOD_CHECK,
            'PID'    => $documentId,
        ));

        if ( $this->createRequest($parameterList) ) {
            $response = $this->parseXmlResponse($this->response);

            return $response;
        }
        else {
            return false;
        }
    }

    private function createRequest ($parameterList, $rawParameterList = array()) {
        $url = parse_url(self::API_URL);

        $host = $url['host'];
        $path = $url['path'];

        $boundary = '---------------------' . substr( md5( rand(0, 32000) ), 0, 10 );

        if ( $socket = fsockopen("ssl://$host", 443, $errorCode, $errorMessage, 300) ) {
            fputs($socket, "POST $path HTTP/1.0\n");
            fputs($socket, "Host: $host\n");
            fputs($socket, "Content-type: multipart/form-data; boundary=$boundary\n");

            $postData = '';

            if ( !empty($parameterList) ) {
                foreach ( $parameterList as $parameterName => $parameterValue ) {
                    $postData .= "--$boundary\n";
                    $postData .= "Content-Disposition: form-data; name=\"$parameterName\"\n\n$parameterValue\n";
                }
            }

            if ( !empty($rawParameterList) ) {
                foreach ( $rawParameterList as $rawParameter ) {
                    $postData .= "--$boundary\n";
                    $postData .= $rawParameter;
                }
            }

            $postData .= "--$boundary--\n";

            fputs($socket, "Content-length: ".strlen($postData)."\n\n");
            fputs($socket, $postData);

            $response = '';

            while ( !feof($socket) ) {
                $response .= fread($socket, 1);
            }

            fclose($socket);

            $this->response = $response;

            return true;
        }
        else {
            $this->errorCode = $errorCode;
            $this->errorMessage = $errorMessage;

            return false;
        }
    }

    private function createParameters ($parameterList) {
        $parameterList = array_merge( array(
            'USER'    => $this->username,
            'KEY'     => $this->apiKey,
            'VERSION' => self::API_VERSION,
        ), $parameterList );

        return $parameterList;
    }

    private function parseXmlResponse ($response) {
        list($header, $content) = explode("\r\n\r\n", $response, 2);

        try {
            return new SimpleXMLElement($content);
        }
        catch(Exception $e) {
            return false;
        }
    }
}