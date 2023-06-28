<?php

namespace Tests\Support\Payload\Admin\Lists;

use Tests\Support\Payload\Pastoral\FormTrait;
use Tests\Support\Payload\PostTrait;

class PastoralActions extends \Tests\Support\Payload\Request
{
    use PostTrait;
    use FormTrait;

    public function __construct(string $pastoralName, int $sequence)
    {
        $this->setReferer("/adminv2/lists/pastoral-action");
        $this->setFormParam('table', 'pastoral-action');
        $sequencePost = $sequence + 1;
        $this->setFormParam('rows', '[{
        "name":"' . $pastoralName . '",
        "externalCode":null,
        "sequence":' . $sequencePost . ',
        "id":"-1",
        "isNew":true
        }]');
    }

    protected function getUrl(): string
    {
        return "/adminv2/lists/save";
    }
}
