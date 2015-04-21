<?php
use Schoolbox\Assessment\Component\SubmissionBox\Submission;

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */
class Plagscan_Gateway
{

    const API_VERSION = '2.0';
    const API_URL     = 'https://api.plagscan.com/';

    const METHOD_SUBMIT   = 'Submit';
    const METHOD_RETRIEVE = 'Retrieve';
    const METHOD_CHECK    = 'Check';
    const METHOD_DELETE   = 'Delete';

    const MODE_RETRIEVE_STATISTICS_ONLY = 0;

    const MODE_RETRIEVE_PLAINTEXT = 1;
    const MODE_RETRIEVE_XML       = 2;
    const MODE_RETRIEVE_DOCX      = 3;

    const MODE_RETRIEVE_HTML_ANNOTATED   = 4;
    const MODE_RETRIEVE_HTML_SOURCES     = 5;
    const MODE_RETRIEVE_HTML_INTERACTIVE = 6;

    const MODE_RETRIEVE_PDF_ANNOTATED = 8;
    const MODE_RETRIEVE_PDF_SOURCES   = 9;

    private $username;

    private $apiKey;

    private $errorCode;

    private $errorMessage;

    private $response;

    public function __construct($username, $apiKey)
    {
        $this->username = $username;
        $this->apiKey   = $apiKey;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }


    /**
     * @param Submission\File $submission
     * @return string
     */
    public function submit(Submission\File $submission)
    {
        $documentName = $submission->getFilename();

        /** @var Schoolbox\Resource\Storage\Model $file */
        $file = $submission->getStorage();
        $documentData = $file->read();

        return $this->postForToken($documentName, $documentData);
    }


    /**
     * POSTs the given filedata to Plagscan, and returns the ID assigned
     *
     * @param string $documentName
     * @param string $documentData
     * @return string
     */
    protected function postForToken($documentName, $documentData) {
        $parameterList = $this->createParameters([
            'METHOD' => self::METHOD_SUBMIT
        ]);

        $documentParameter = '';

        $fileInfo = new finfo(FILEINFO_MIME);

        $documentType = $fileInfo->buffer($documentData);

        $documentExtension = pathinfo($documentName, PATHINFO_EXTENSION);

        $validDocumentExtensionList = array(
            'docx',
            'doc',
            'pdf',
            'txt',
            'rtf',
            'html',
        );

        if (!in_array($documentExtension, $validDocumentExtensionList)) {
            return null;
        }

        $documentParameter .= "Content-Disposition: form-data; name=\"DATA\"; filename=\"$documentName\"\n";
        $documentParameter .= "Content-Type: $documentType\n";
        $documentParameter .= "Content-Transfer-Encoding: binary\n\n";
        $documentParameter .= "$documentData\n";

        $this->createRequest($parameterList, array($documentParameter));

        $response = $this->parseXmlResponse($this->response);

        return (string)$response->PID;
    }

    /**
     * Data Retrieval is based on $retrieveMode, each mode will return a different set of results
     *
     * @param $documentId
     * @param $retrieveMode
     *
     * @return bool|SimpleXMLElement
     */
    public function retrieve($documentId, $retrieveMode)
    {
        $parameterList = $this->createParameters(
            array(
                'METHOD' => self::METHOD_RETRIEVE,
                'PID'    => $documentId,
                'MODE'   => $retrieveMode
            )
        );

        if ($this->createRequest($parameterList)) {
            if ($retrieveMode == self::MODE_RETRIEVE_PLAINTEXT) {
                // plaintext
                return $this->parsePlainResponse($this->response);
            } elseif ($retrieveMode < self::MODE_RETRIEVE_DOCX) {
                // xml
                return $this->parseXmlResponse($this->response);
            } elseif ($retrieveMode == self::MODE_RETRIEVE_DOCX
                || $retrieveMode > self::MODE_RETRIEVE_HTML_INTERACTIVE
            ) {
                // file download
                return $this->parseFileResponse($this->response);
            } else {
                // html
                return $this->parseHtmlResponse($this->response);
            }
        } else {
            return false;
        }
    }

    public function check($documentId)
    {
        $parameterList = $this->createParameters(
            array(
                'METHOD' => self::METHOD_CHECK,
                'PID'    => $documentId,
            )
        );

        if ($this->createRequest($parameterList)) {
            $response = $this->parseXmlResponse($this->response);
            return $response;
        } else {
            return false;
        }
    }

    public function delete($documentId)
    {
        $parameterList = $this->createParameters(
            array(
                'METHOD' => self::METHOD_DELETE,
                'PID'    => $documentId,
            )
        );

        if ($this->createRequest($parameterList)) {
            $response = $this->parseXmlResponse($this->response);
            return $response;
        } else {
            return false;
        }
    }

    private function createRequest($parameterList, $rawParameterList = array())
    {
        $url = parse_url(self::API_URL);

        $host = $url['host'];
        $path = $url['path'];

        $boundary = '---------------------' . substr(md5(rand(0, 32000)), 0, 10);

        // build a proxy context (will be null if proxy isn't configured)
        $context = Proxy\Server::getInstance()->getContext('https');
        if ($socket = stream_socket_client(
            "ssl://$host:443",
            $errorCode,
            $errorMessage,
            300,
            STREAM_CLIENT_CONNECT,
            $context
        )) {
            fputs($socket, "POST $path HTTP/1.0\n");
            fputs($socket, "Host: $host\n");
            fputs($socket, "Content-type: multipart/form-data; boundary=$boundary\n");

            $postData = '';

            if (!empty($parameterList)) {
                foreach ($parameterList as $parameterName => $parameterValue) {
                    $postData .= "--$boundary\n";
                    $postData .= "Content-Disposition: form-data; name=\"$parameterName\"\n\n$parameterValue\n";
                }
            }

            if (!empty($rawParameterList)) {
                foreach ($rawParameterList as $rawParameter) {
                    $postData .= "--$boundary\n";
                    $postData .= $rawParameter;
                }
            }

            $postData .= "--$boundary--\n";

            fputs($socket, "Content-length: " . strlen($postData) . "\n\n");
            fputs($socket, $postData);

            $response = '';

            while (!feof($socket)) {
                $response .= fread($socket, 1);
            }

            fclose($socket);

            $this->response = $response;

            return true;
        } else {
            $this->errorCode    = $errorCode;
            $this->errorMessage = $errorMessage;

            return false;
        }
    }

    private function createParameters($parameterList)
    {
        $parameterList = array_merge(
            array(
                'USER'    => $this->username,
                'KEY'     => $this->apiKey,
                'VERSION' => self::API_VERSION,
            ),
            $parameterList
        );

        return $parameterList;
    }

    private function parseXmlResponse($response)
    {
        list($header, $content) = explode("\r\n\r\n", $response, 2);

        try {
            // unfortunately try-catch isn't enough here, temporarily set a custom error handler for this scenario
            set_error_handler(
                function ($errno, $errstr, $errfile, $errline, $errcontext) {
                    throw new ErrorException($errstr, $errno, 1, $errfile, $errline);
                }
            );
            $sxe = new SimpleXMLElement($content);
            restore_error_handler();
            return $sxe;
        } catch (Exception $e) {
            restore_error_handler();
            return false;
        }
    }

    private function parsePlainResponse($response)
    {
        list($header, $content) = explode("\r\n\r\n", $response, 2);
        return $content;
    }

    private function parseFileResponse($response)
    {
        return $response;
    }

    private function parseHtmlResponse($response)
    {
        list($header, $content) = explode("\r\n\r\n", $response, 2);
        return $content;
    }
}
