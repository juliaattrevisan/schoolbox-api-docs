<?php

namespace Payload\Admin\Lists;

use Payload\Pastoral\FormTrait;
use Payload\PostTrait;

class PastoralSubType extends \Payload\Request
{
    use PostTrait;
    use FormTrait;

    public function __construct(int $pastoralType, string $subTypeName, int $sequence)
    {
        $this->setReferer("/adminv2/lists/pastoral-category");
        $this->setFormParam('table', 'pastoral-category');
        $sequencePost = $sequence + 1;
        $this->setFormParam('rows', '[{
        "pastoralType":' . $pastoralType . ',
        "name":"' . $subTypeName . '",
        "externalCode":null,
        "sequence":' . $sequencePost . ',
        "id":"0",
        "isNew":true
        }]');
    }

    protected function getUrl(): string
    {
        return "/adminv2/lists/save";
    }
}
