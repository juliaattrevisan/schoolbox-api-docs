<?php

namespace Tests\Support\Payload\Admin\Modules;

use Tests\Support\Payload\Pastoral\FormTrait;
use Tests\Support\Payload\PostTrait;

class Packages extends \Tests\Support\Payload\Request
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
