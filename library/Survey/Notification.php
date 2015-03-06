<?php
use Schoolbox\UserMapper;

/**
 * Survey_Notification
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Survey_Notification
{
    /** @var string */
    protected $emailTemplateFile = '../survey/email/authorNotification.html';

    /** @var Mailer */
    protected $email = null;

    /** @var array */
    private $survey = array();

    /** @var User */
    private $userHandler = null;

    /**
     * Constructor
     *
     * @param $survey
     */
    public function __construct($survey)
    {
        $this->setSurvey($survey);
        $this->setUserHandler(new User());

        $this->setup();
    }

    /**
     * Setup
     */
    public function setup()
    {
        $survey = $this->getSurvey();

        $author = $this->getUserHandler()->getByID($this->getAuthor());
        $subject = "A response for {$this->getSurveyName()} was submitted";

        $email = new Mailer($this->getEmailTemplateFile(), $subject, $author['alt_email']);

        $email->setVar(array(
            'SURVEY_SURVEY_ID' => $survey['assess_id'],
            'SURVEY_TITLE' => Scrub::text($survey['title']),
        ));

        $this->setEmail($email);
    }

    /**
     * Send
     */
    public function send()
    {
        $this->getEmail()->send();
    }

    /* Getters/Setters
      */

    /**
     * Get author id
     *
     * @return mixed
     */
    private function getAuthor()
    {
        return $this->survey['user_id'];
    }

    /**
     * Get survey name
     *
     * @return mixed
     */
    private function getSurveyName()
    {
        return $this->survey['title'];
    }

    /**
     * Get email template file
     *
     * @return string
     */
    protected function getEmailTemplateFile()
    {
        return $this->emailTemplateFile;
    }

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
     * Set survey
     *
     * @param array $survey
     */
    private function setSurvey($survey)
    {
        $this->survey = $survey;
    }

    /**
     * Get survey
     *
     * @return array
     */
    private function getSurvey()
    {
        return $this->survey;
    }

    /**
     * Set user handler
     *
     * @param User $userHandler
     */
    private function setUserHandler($userHandler)
    {
        $this->userHandler = $userHandler;
    }

    /**
     * Get user handler
     *
     * @return User
     */
    private function getUserHandler()
    {
        return $this->userHandler;
    }
}
