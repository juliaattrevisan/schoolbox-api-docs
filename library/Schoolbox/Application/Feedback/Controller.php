<?php

/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Feedback;

use Alaress\Web\Controller\Base;
use Schoolbox\User;
use ReCaptcha;

class Controller extends Base
{
    /** @var ReCaptcha $reCaptcha */
    private $reCaptcha;

    /**
     * Controller constructor.
     *
     * @param ReCaptcha               $reCaptcha
     */
    public function __construct(ReCaptcha $reCaptcha)
    {
        $this->reCaptcha = $reCaptcha;
    }


    /**
     * Provides a form that users can use to submit support requests or feedback via.  Can be accessed without session
     * @api   GET /feedback
     * @since 18.2
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getFeedback(): void
    {
        $data = [];
        $authUser = $this->getSession()->getAuthUser();
        if ($authUser instanceof User && !$authUser->getId()) {
            $data = ['recaptcha' => $this->reCaptcha->form()];
        }

        $data['types'] = explode(',', $this->getConfigLoader()->get('site_feedback_types'));

        $this->getResponse()->content->set($this->buildHtmlOutput('@feedback/feedback.twig', $data)
                                                ->setTitle($this->getConfigLoader()->get('feedback_text')));
    }

    /**
     * Thank you page after submitting feedback, can be accessed with and without a session
     * @api   GET /feedback/thank-you
     * @since 18.2
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getFeedbackReceipt()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@feedback/feedback-thankyou.twig', [])
                                                ->setTitle('Message Delivered'));
    }
}
