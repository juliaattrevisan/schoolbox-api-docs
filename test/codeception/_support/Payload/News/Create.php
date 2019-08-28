<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload;

use Schoolbox\Comms\News\Querystring;

class CreateNews extends Request
{

    public function __construct()
    {
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
