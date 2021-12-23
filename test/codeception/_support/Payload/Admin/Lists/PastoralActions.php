<?php

namespace Payload\Admin\Lists;

use Payload\Pastoral\FormTrait;
use Payload\PostTrait;

class PastoralActions extends \Payload\Request
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
