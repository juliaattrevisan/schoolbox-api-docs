<?php

/**
 * Leave_Core_Notice_Parent_Abstract
 *
 * The base class for parent notifications.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Symfony\Component\Mime\Exception\RfcComplianceException;
use Schoolbox\Admin\EmailDKIM\Mapper as DKIMMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Mail\EmailFactory;

abstract class Leave_Core_Notice_Parent_Abstract extends Leave_Notice_Abstract_Notice
{
    protected ?\Schoolbox\Mail\TwigEmail $email = null;

    protected string $emailTemplateFile = 'leave/parentNoticeEmail.twig';

    private ?External_User $externalUser = null;

    /**
     * Setup
     */
    public function setup()
    {
        /* For getting all the parents of a child
         */
        $this->externalUser = new External_User();

        $subject = '(Parent) Leave Request Status Update for ' . $this->getRequest()->getUser()->formatName();
        $subject = '(Student) Leave Request Status Update for ' . $this->getRequest()->getUser()->formatName();

        $twigEmailFactory = new EmailFactory(ConfigLoader::getInstance(), DKIMMapper::getInstance());
        $email = $twigEmailFactory->buildTwigEmail($this->emailTemplateFile, $subject, $this->getRequest()->getUser());

        $context = [
            'LEAVE_USER_NAME'      => $this->getRequest()->getUser()->formatName(),
            'LEAVE_AUTHOR_NAME'    => $this->getRequest()->getAuthor()->formatName(),
            'LEAVE_STATUS'         => $this->getRequest()->getStatus(true),
            'LEAVE_START'          => $this->getRequest()->getStart()->getLong(),
            'LEAVE_FINISH'         => $this->getRequest()->getFinish()->getLong(),
            'LEAVE_DESCRIPTION'    => $this->getRequest()->getDescription(),
            'LEAVE_HOST_NAME'      => $this->getRequest()->getHostName(),
            'LEAVE_HOST_PHONE'     => $this->getRequest()->getHostPhone(),
            'LEAVE_STUDENT_PHONE'  => $this->getRequest()->getUserPhone(),
            'LEAVE_HOST_MOBILE'    => $this->getRequest()->getHostMobile(),
            'LEAVE_HOST_ADDRESS'   => $this->getRequest()->getHostAddress(),
            'LEAVE_COLLECTOR_NAME' => $this->getRequest()->getCollectorName(),
            'LEAVE_RETURNER_NAME'  => $this->getRequest()->getReturnerName(),
            'LEAVE_REASON'         => $this->getRequest()->getReason(),
            'TERMS_AND_CONDITIONS' => Config::get('terms_and_conditions'),
        ];

        $email->setContext($context);
        $this->email = $email;
    }

    /**
     * Send a notification to all parents of the child
     */
    public function send()
    {
        $allParents = $this->externalUser->getStudentContactList([$this->getRequest()->getUser()->getExternalId()]);

        if (is_array($allParents)) {
            $emailList = array();

            foreach ($allParents as $parent) {
                $email1 = $parent['StudentContactDefaultEmail'];
                if (!in_array($email1, $emailList, true)) {
                    $emailList[] = $email1;
                }
                $email2 = $parent['StudentContactSpouseDefaultEmail'];
                if (!in_array($email2, $emailList)) {
                    $emailList[] = $email2;
                }
            }

            foreach ($emailList as $parentEmail) {
                if (Config::get('debug_email_address') != '') {
                    $email = new \Email(Config::get('debug_email_address'));
                } else {
                    $email = new \Email($parentEmail);
                }

                /* Only send if the email is valid
                 */
                if ($email->isValid()) {
                    // Ensure the current user has a valid email too
                    try {
                        $this->email->setFromAsCurrentUser();
                    } catch (RfcComplianceException $e) {
                        // noop - falls back to whatever the default address is
                    }
                    $this->email->send($email->getEmail());
                }
            }
        }
    }
}
