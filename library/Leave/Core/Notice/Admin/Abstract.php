<?php

/**
 * Base class for email notifications.
 *
 * @author     Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright  Copyright 2019, Schoolbox Pty Ltd
 * @link       https://schoolbox.com.au/
 * @copiedfrom Steve Chan <steve.chan@alaress.com.au>
 */

use Schoolbox\Admin\EmailDKIM\Mapper as DKIMMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Mail\EmailFactory;

abstract class Leave_Core_Notice_Admin_Abstract extends Leave_Notice_Abstract_Notice
{
    protected ?\Schoolbox\Mail\TwigEmail $email = null;

    protected string $emailTemplateFile = 'leave/adminNoticeEmail.twig';

    /**
     * Setup
     */
    public function setup()
    {
        $subject = '(Admin) Leave Request Status Update for ' . $this->getRequest()->getUser()->formatName();

        $emailFactory = new EmailFactory(ConfigLoader::getInstance(), DKIMMapper::getInstance());
        $email            = $emailFactory->buildTwigEmail($this->emailTemplateFile, $subject, $this->getRequest()->getUser());

        $context = [
            'LEAVE_USER_NAME'     => $this->getRequest()->getUser()->formatName(),
            'LEAVE_AUTHOR_NAME'   => $this->getRequest()->getAuthor()->formatName(),
            'LEAVE_STATUS'        => $this->getRequest()->getStatus(true),
            'LEAVE_START'         => $this->getRequest()->getStart()->getLong(),
            'LEAVE_FINISH'        => $this->getRequest()->getFinish()->getLong(),
            'LEAVE_DESCRIPTION'   => $this->getRequest()->getDescription(),
            'LEAVE_HOST_NAME'     => $this->getRequest()->getHostName(),
            'LEAVE_HOST_PHONE'    => $this->getRequest()->getHostPhone(),
            'LEAVE_STUDENT_PHONE' => $this->getRequest()->getUserPhone(),
            'hideUnsubscribe'     => true,
        ];

        $email->setContext($context);
        $this->email = $email;
    }

    /**
     * Send it - by default, sends nothing (because most people don't have admin
     * messages set up
     */
    public function send()
    {
        return;
    }
}
