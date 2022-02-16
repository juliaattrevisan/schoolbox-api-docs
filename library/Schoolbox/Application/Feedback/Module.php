<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Feedback;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Twig\Error\LoaderError;
use ReCaptcha;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'feedback';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes()
    {
        $this->register('GET', '', 'getFeedback', null, null);
        $this->register('GET', '/thank-you', 'getFeedbackReceipt', null, null);
    }

    /**
     * @param IChainLoader $templateLoader
     * @throws LoaderError
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            new ReCaptcha(
                $this->getConfig()->get('recaptcha_public_api_key'),
                $this->getConfig()->get('recaptcha_private_api_key'),
            ),
        );
    }
}
