<?php

namespace Payload\Admin\Lists;

use Payload\Pastoral\FormTrait;
use Payload\PostTrait;

class PastoralTypes extends \Payload\Request
{
    use PostTrait;
    use FormTrait;

    public function __construct(string $typeName, int $sequence)
    {
        $this->setReferer("/adminv2/lists/pastoral-type");
        $this->setFormParam('table', 'pastoral-type');
        $sequencePost = $sequence + 1;
        $this->setFormParam('rows', '[{
        "name":"' . $typeName . '",
        "documentClassification":null,
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
