<?php
/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Site;

use Alaress\Web\Controller\Base;
use ReCaptcha;
use Schoolbox\User;

class Controller extends Base
{

    /**
     * @var \Schoolbox\Error\Mapper
     */
    private $errorMapper;

    /** @var ReCaptcha $reCaptcha */
    private $reCaptcha;

    /**
     * Controller constructor.
     *
     * @param \Schoolbox\Error\Mapper $errorMapper
     * @param ReCaptcha $reCaptcha
     */
    public function __construct(\Schoolbox\Error\Mapper $errorMapper, ReCaptcha $reCaptcha)
    {
        $this->errorMapper = $errorMapper;
        $this->reCaptcha = $reCaptcha;
    }

    /**
     * Provides a form that users can use to submit support requests or feedback via
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getFeedback()
    {
        $data = [];
        $authUser = $this->getSession()->getAuthUser();
        if ($authUser instanceof User && !$authUser->getId() ) {
            $data = ['recaptcha' => $this->reCaptcha->form()];
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@site/feedback.twig', $data)->setTitle($this->getConfigLoader()->get('feedback_text')));
    }

    /**
     * Load $templateName
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getFeedbackReceipt()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@site/feedback-thankyou.twig', [])->setTitle('Message Delivered'));
    }


    /**
     * Load $templateName
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getPolicy()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@site/policy.twig', []));
    }

    /**
     * Load $templateName
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getLogin()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@site/login.twig', []));
    }
}
