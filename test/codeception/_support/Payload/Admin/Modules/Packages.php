<?php

namespace Payload\Admin\Modules;

use Payload\Pastoral\FormTrait;
use Payload\PostTrait;

class Packages extends \Payload\Request
{
    use PostTrait;
    use FormTrait;

    public function __construct(int $id)
    {
        $this->setReferer("/adminv2/package");
        $this->setFormParam('id', $id);

        $this->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->setAccepts('application/json, text/javascript, */*; q=0.01');
    }

    protected function getUrl(): string
    {
        return "/adminv2/package/install";
    }
}
