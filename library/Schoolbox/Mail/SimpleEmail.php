<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Mail;

use Alaress\Mail\AbstractMailer;

/**
 * Class TwigEmail
 *
 * @package Schoolbox\Mail
 */
class SimpleEmail extends AbstractMailer
{
    protected function composeHTML()
    {
        return (string)$this->email->getHtmlBody();
    }

    protected function composePlain()
    {
        return (string)$this->email->getTextBody();
    }
}
