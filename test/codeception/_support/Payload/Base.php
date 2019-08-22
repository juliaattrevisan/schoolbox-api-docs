<?php
declare(strict_types=1);

namespace Payload;

/**
 * A payload encapsulates:
 * * a HTTP method
 * * a URL
 * * HTTP headers
 *
 */
class Base
{
    protected const VAGRANT_DOMAIN = 'vagrant.dev.schoolbox.cloud';

    private $structure = [
        'method'  => 'POST',
        'page'    => 'https://'.self::VAGRANT_DOMAIN,
        'payload' => [
            'headers'     => [
                'Host'          => self::VAGRANT_DOMAIN,
                'Content-Type'  => 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept'        => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Referer'       => 'https://'.self::VAGRANT_DOMAIN
            ],
            'form_params' => [],
            'query' => []
        ]
    ];

    public function __construct()
    {
        unset($this->structure['payload']['query']);
        /**
         * Example code for setting various parts of the payload in classes that extend this.
         * $this->setPage('/page_we_desplay');
         * $this->setReferer('/page_where_we_came_from');
         * $this->setFormParam('attributeKey1', 'attributeData1');
         * $this->setFormParam('attributeKey2', 'attributeData2');
         */
    }

    protected function setPageURL(string $page)
    {
        $this->structure['page']=$page;
    }

    public function setPage(string $page)
    {
        $this->structure['page']='https://'.self::VAGRANT_DOMAIN.$page;
    }

    public function setMethod(string $method)
    {
        $this->structure['method']=$method;
    }

    protected function setHeader(string $key, string $value)
    {
        $this->structure['payload']['headers'][$key]=$value;
    }

    public function setReferer(string $page)
    {
        $this->setHeader('Referer', 'https://'.self::VAGRANT_DOMAIN.$page);
    }

    public function getHeader(string $key)
    {
        return $this->structure['payload']['headers'][$key];
    }

    public function removeHeader(string $key)
    {
        unset($this->structure['payload']['headers'][$key]);
    }

    public function getFormParam(string $key)
    {
        if (array_key_exists($key, $this->structure['payload']['form_params']))
        {
            return $this->structure['payload']['form_params'][$key];
        }

        return null;
    }

    public function setFormParam(string $key, $value)
    {
        $this->structure['payload']['form_params'][$key]=$value;
    }

    public function removeFormParam(string $key)
    {
        unset($this->structure['payload']['form_params'][$key]);
    }

    public function clearAllFormParams()
    {
        $this->structure['payload']['form_params']=[];
    }

    protected function deleteFormParams()
    {
        unset($this->structure['payload']['form_params']);
    }

    public function setQueryString(string $key, ?string $value)
    {
        $this->structure['payload']['query'][$key]=$value;
    }

    public function removeQueryString(string $key)
    {
        unset($this->structure['payload']['query'][$key]);
    }

    public function payloadToArray()
    {
        return $this->structure;
    }
}
