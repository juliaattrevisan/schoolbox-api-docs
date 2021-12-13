<?php

namespace Payload\Homepage\Component;

use Payload\Pastoral\FormTrait;
use Payload\PutTrait;
use Payload\Request;

class TextBoxModify extends Request
{
    use PutTrait;
    use FormTrait;

    /**
     * @var int
     */
    private int $homepageId;
    private int $folderId;

    public function __construct(int $homepageId, int $folderId)
    {
        $this->homepageId =  $homepageId;
        $this->folderId =  $folderId;
        $this->setReferer("/homepage/$homepageId");
        $this->setHeading('');
        $this->setBody('');
        $this->setHeader('content-type', 'application/json');
        $this->setAccepts('application/json, text/javascript, */*; q=0.01');
    }

    public function setHeading(string $heading): void
    {
        $this->setFormParam('heading', $heading);
    }

    public function setBody(string $text): void
    {
        $this->setFormParam('body', $text);
    }

    protected function getUrl(): string
    {

        return "/textbox/{$this->folderId}";
    }
}
