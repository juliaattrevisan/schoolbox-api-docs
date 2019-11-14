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
abstract class Leave_Core_Notice_Parent_Abstract extends Leave_Notice_Abstract_Notice
{
    /**
     * @var Mailer
     */
    protected $email = null;
    protected $emailTemplateFile = 'modules/leave/parentNoticeEmail.html';

    private $externalUser = null;

    /**
     * Setup
     */
    public function setup()
    {

        /* For getting all the parents of a child
         */
        $this->setExternalUser(new External_User());

        $subject = '(Parent) Leave Request Status Update for ' . $this->getRequest()->getUser()->formatName();

        /* Set destination email address in loop in send()
         */
        $email = new Mailer(
            Mailer::translateTemplatePathToMailPath($this->getEmailTemplateFile()),
            $subject,
            ''
        );

        $email->setBlock($email->t_label, 'submitted_by', 'submitted_by_block');
        $email->setBlock($email->t_label, 'authorised_by', 'authorised_by_block');
        $email->setBlock($email->t_label, 'rejected_by', 'rejected_by_block');

        $email->setVar(
            array(
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
            )
        );

        $this->setEmail($email);
    }

    /**
     * Send a notification to all parents of the child
     */
    public function send()
    {
        $allParents = $this->getExternalUser()->getStudentContactList([$this->getRequest()->getUser()->getExternalId()]);

        if (is_array($allParents)) {
            $emailList = Array();

            foreach ($allParents as $parent) {
                $email1 = $parent['StudentContactDefaultEmail'];
                if (!in_array($email1, $emailList, true)){
                    $emailList[] = $email1;
                }
                $email2 = $parent['StudentContactSpouseDefaultEmail'];
                if (!in_array($email2, $emailList)){
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
                        $this->getEmail()->setFromAsCurrentUser();
                    } catch (Swift_SwiftException $e) {
                        // noop - falls back to whatever the default address is
                    }
                    $this->getEmail()->send($email->getEmail());
                }
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * Set email
     *
     * @param Mailer $email
     */
    protected function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * Get email
     *
     * @return Mailer
     */
    protected function getEmail()
    {
        return $this->email;
    }

    /**
     * @param $externalUser
     */
    private function setExternalUser($externalUser)
    {
        $this->externalUser = $externalUser;
    }

    /**
     * @return null
     */
    private function getExternalUser()
    {
        return $this->externalUser;
    }
}
