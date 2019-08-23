<?php


namespace Payload;


class GetPage extends Base
{
    public function __construct()
    {
        Base::__construct();
        $this->setPage('/??');

        $this->setReferer('/');
        $this->deleteFormParams();
        $this->setMethod('GET');
    }

    public function setURL( string $PageUrl)
    {
        $this->setPageURL($PageUrl);
    }
}
