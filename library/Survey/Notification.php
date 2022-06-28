<?php

/**
 * Survey_Notification
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Url\Factory as UrlFactory;
use Schoolbox\UserMapper;

class Survey_Notification
{
    /** @var \Schoolbox\Mail\TwigEmail|null */
    protected ?\Schoolbox\Mail\TwigEmail $email = null;

    /** @var array */
    private array $survey;

    /** @var array */
    private array $attempt;

    /** @var UserMapper */
    private UserMapper $userMapper;

    private \Schoolbox\Config\Loader $config;

    private \Schoolbox\Mail\EmailFactory $emailFactory;

    /**
     * Constructor
     *
     * @param array $survey
     * @param array $attempt
     */
    public function __construct(array $survey, array $attempt, \Schoolbox\Mail\EmailFactory $emailFactory)
    {
        $this->survey     = $survey;
        $this->attempt    = $attempt;
        $this->userMapper = UserMapper::getInstance();
        $this->config     = \Schoolbox\Config\Loader::getInstance();

        $this->emailFactory = $emailFactory;

        $this->setup();
    }

    /**
     * Setup
     */
    public function setup()
    {
        // Load first section for link to review page
        $sectionHandler = new Assess_Section();
        $firstSection   = $sectionHandler->getFirst($this->survey['assess_id']);

        try {
            $author = $this->userMapper->findById($this->getAuthorId());
        } catch (\Schoolbox\Mapper\Exception $e) {
            return;
        }
        if (empty($author->getEmail())) {
            return;
        }
        $subject = "A response for {$this->getSurveyTitle()} was submitted";

        $baseUrl = (new UrlFactory($this->config))->buildFullyQualified();
        $email   = $this->emailFactory->buildTwigEmail('instantmail.twig', $subject, $author->getEmail());
        $email->setContext(
            [
                'hideUnsubscribe' => true,
                'recipient' => $author->getFullName(),
                'groups'    => [
                    [
                        'rows' => [
                            [
                                'messages' => [
                                    [
                                        'icon'  => $baseUrl->setPath('/images/icon.php')->setQuery(['size'=>64, 'icon'=>'report'])->toString(),
                                        'title' => sprintf('A response was submitted to %s. You may access this response', Scrub::text($this->getSurveyTitle())),
                                        'href'  => $baseUrl->setPath(sprintf('/survey/review/%d/%d/%d', $this->survey['assess_id'], $firstSection['section_id'], $this->attempt['attempt_id']))->toString(),
                                        'meta'  => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );
        $this->email = $email;
    }

    /**
     * Send
     */
    public function send()
    {
        if ($this->email) {
            $this->email->send();
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
}
