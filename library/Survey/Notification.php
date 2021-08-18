<?php

use Schoolbox\UserMapper;

/**
 * Survey_Notification
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Notification
{
    /** @var string */
    protected $emailTemplateFile = '../survey/email/authorNotification.html';

    /** @var Mailer */
    protected $email = null;

    /** @var array */
    private $survey = array();

    /** @var array */
    private $attempt = array();

    /** @var UserMapper */
    private $userMapper = null;

    /**
     * Constructor
     *
     * @param array $survey
     * @param array $attempt
     */
    public function __construct($survey, $attempt)
    {
        $this->setSurvey($survey);
        $this->setAttempt($attempt);
        $this->setUserMapper(UserMapper::getInstance());

        $this->setup();
    }

    /**
     * Setup
     */
    public function setup()
    {
        $survey  = $this->getSurvey();
        $attempt = $this->getAttempt();
        // Load first section for link to review page
        $sectionHandler = new Assess_Section();
        $firstSection = $sectionHandler->getFirst($survey['assess_id']);

        $author = $this->getUserMapper()->findByIdWithFallback($this->getAuthorId());
        $subject = "A response for {$this->getSurveyTitle()} was submitted";

        if ($author->getEmail() != null &&  $author->isDeleted() === false) {
            $email = new Mailer($this->getEmailTemplateFile(), $subject, $author->getEmail());

            $email->setVar([
                               'SURVEY_SURVEY_ID'   => $survey['assess_id'],
                               'SURVEY_TITLE'       => Scrub::text($survey['title']),
                               'ATTEMPT_SECTION_ID' => $firstSection['section_id'],
                               'ATTEMPT_ATTEMPT_ID' => $attempt['attempt_id'],
                           ]);

            $this->setEmail($email);
        }
    }

    /**
     * Send
     */
    public function send()
    {
        if ($this->email) {
            $this->getEmail()->send();
        }
    }

    /* Getters/Setters
      */

    /**
     * Get author id
     *
     * @return mixed
     */
    private function getAuthorId()
    {
        return $this->survey['user_id'];
    }

    /**
     * Get survey name
     *
     * @return mixed
     */
    private function getSurveyTitle()
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
     * @return array
     */
    private function getAttempt()
    {
        return $this->attempt;
    }

    /**
     * @param  array $attempt
     *
     * @return $this
     */
    private function setAttempt($attempt)
    {
        $this->attempt = $attempt;
        return $this;
    }

    /**
     * Set user handler
     *
     * @param UserMapper $userMapper
     */
    private function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Get user handler
     *
     * @return UserMapper
     */
    private function getUserMapper()
    {
        return $this->userMapper;
    }
}
