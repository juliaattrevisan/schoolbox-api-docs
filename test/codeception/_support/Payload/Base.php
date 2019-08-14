<?php


namespace Payload;

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
            'form_params' => []
        ]
    ];

    public function __construct()
    {
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
        return $this->structure['payload']['form_params'][$key];
    }

    public function setFormParam(string $key, string $value)
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

    public function payloadToArray()
    {
        return $this->structure;
    }
}