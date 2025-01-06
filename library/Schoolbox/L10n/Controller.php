<?php

namespace Schoolbox\L10n;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * @var RosettaStone
     */
    private $rosettaStone;

    /**
     * @param RosettaStone $rosettaStone
     */
    public function __construct(RosettaStone $rosettaStone)
    {
        $this->rosettaStone = $rosettaStone;
    }

    public function getLocale()
    {
        $this->response = $this->responseAsJson($this->rosettaStone->getAllTermsArray());
    }
}
