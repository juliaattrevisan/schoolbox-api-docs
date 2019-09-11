<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload;

/**
 * Common implementation for all requests.
 *
 * A request encapsulates:
 * - The HTTP method used to send the request
 * - The URL the request is sent to
 * - The HTTP referer header
 * - The request payload/form details
 *
 * Constructor params should align to the logical items that the system requires to generate the form.
 */
abstract class Request
{
    // TODO this is already logically declared in codeception.dist.yml, should re-used that value
    private const VAGRANT_DOMAIN = 'vagrant.dev.schoolbox.cloud';

    private $headers = [
        'Host'         => self::VAGRANT_DOMAIN,
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Accept'       => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ];

    /** @var array */
    private $queryParams = [];

    /**
     * Note that if you send a request as 'GET', then form params and query params are both 'GET' params.
     * If you send the request as 'POST', they are considered distinct.
     * @var array
     */
    private $formParams = [];


    /** @var mixed[][] */
    private $files = [];


    /**
     * Returns this request's headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Sets the referer header to the specified path
     *
     * @param string $path
     * @return $this
     */
    public function setReferer(string $path): self
    {
        $this->setHeader('Referer', 'https://' . self::VAGRANT_DOMAIN . $path);
        return $this;
    }

    /**
     * Returns this request's HTTP method
     *
     * @return string
     */
    abstract public function getMethod(): string;

    /**
     * Returns the Schoolbox URL (including query params) to send this request to
     *
     * @return string
     */
    public function getUrlWithQueryString(): string
    {
        $url         = $this->getUrl();
        $queryString = $this->getQueryString();
        if ($queryString) {
            $url .= "?$queryString";
        }
        return $url;
    }

    /**
     * Sets a query param
     *
     * @param string $name
     * @param string $value
     */
    protected function setQueryParam(string $name, $value): void
    {
        $this->queryParams[$name] = $value;
    }

    /**
     * Returns this request's form params
     *
     * @return array
     */
    public function getFormParams(): array
    {
        return $this->formParams;
    }

    /**
     * Set a form param on this request
     *
     * @param string $name  The 'name' attribute of a form field
     * @param mixed  $value The 'value' attribute of a form field
     */
    public function setFormParam(string $name, $value): void
    {
        $this->formParams[$name] = $value;
    }

    /**
     * Unsets a form param if it exists
     *
     * @param string $name
     */
    protected function unsetFormParam(string $name): void
    {
        if (array_key_exists($name, $this->formParams)) {
            unset($this->formParams[$name]);
        }
    }

    /**
     * Returns this request's files
     *
     * @return array
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Adds a file to request.
     *
     * @param string $name     The 'name' attribute of the file input form field, eg 'myAttachmentFile'
     * @param string $fileName eg 'document.pdf'
     * @param string $type     eg 'application/pdf'
     * @param int    $error    One of the UPLOAD_ERR_* constants
     * @param int    $size     eg filesize(codecept_data_dir('sample_file.pdf'))
     * @param string $tempName eg codecept_data_dir('sample_file.pdf')
     * @return $this
     *
     * @see UPLOAD_ERR_OK as an example error code
     */
    public function setFile(string $name, string $fileName, string $type, int $error, int $size, string $tempName): self
    {
        $this->files[$name] = [
            'name'     => $fileName,
            'type'     => $type,
            'error'    => $error,
            'size'     => $size,
            'tmp_name' => $tempName,
        ];
        return $this;
    }

    /**
     * Returns this request's query string
     *
     * @return string
     */
    protected function getQueryString(): string
    {
        if (!count($this->queryParams)) {
            return '';
        }
        return http_build_query($this->queryParams);
    }

    /**
     * Returns the Schoolbox URL (without query params) to send this request to
     *
     * @return string
     */
    abstract protected function getUrl(): string;

    /**
     * Sets a request header
     *
     * @param string $key
     * @param string $value
     * @return $this
     */
    private function setHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }
}
