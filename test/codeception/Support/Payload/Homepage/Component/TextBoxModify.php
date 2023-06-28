<?php

namespace Tests\Support\Payload\Homepage\Component;

use Tests\Support\Payload\Pastoral\FormTrait;
use Tests\Support\Payload\PutTrait;
use Tests\Support\Payload\Request;

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
