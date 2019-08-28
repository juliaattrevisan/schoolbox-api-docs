<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\News;

use Payload\PostTrait;
use Payload\Request;

class Create extends Request
{
    use PostTrait;

    public function __construct(string $returnUrl)
    {

        $this->setReferer('/news/create');

        $this->setFormParam('returnUrl', $returnUrl);
        $this->setNewsTitle('');
        $this->setFormParam('body', '');
        $this->setFormParam('blurb', '');
        $this->setFormParam('audience', '');
        $this->setFormParam('publishAt', date('d/m/Y g:ia'));
        $this->setFormParam('expiresAt', date('d/m/Y g:ia', strtotime('+ 5 days')));
        $this->setFormParam('draft', 'true');
    }

    public function setNewsTitle(string $title): void
    {
        $this->setFormParam('title', $title);
    }

    protected function getUrl(): string
    {
       return '/news/create';
    }
}
