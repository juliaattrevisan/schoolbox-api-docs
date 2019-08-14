<?php


namespace Payload;


use Schoolbox\Comms\News\Querystring;

class CreateNews extends Base
{

    public function __construct()
    {
        Base::__construct();
        $this->setPage('/news/create');
        $this->setReferer('/news/create');
        $this->setFormParam('returnUrl', Querystring::encode('/'));
        $this->setFormParam('title', 'sometitle');
        $this->setFormParam('body', '');
        $this->setFormParam('blurb', '');
        $this->setFormParam('audience', 'role:IjMi:self');
        $this->setFormParam('publishAt', date('d/m/Y g:ia'));
        $this->setFormParam('expiresAt', date('d/m/Y g:ia', strtotime('+ 5 days')));
        $this->setFormParam('draft', 'true');
    }

    public function setNewsTitle(string $title)
    {
        $this->setFormParam('title', $title);
    }

}