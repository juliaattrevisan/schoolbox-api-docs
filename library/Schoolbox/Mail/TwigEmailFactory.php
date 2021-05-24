<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mail;

use Psr\Log\LoggerInterface;

/**
 * Builder for Twig email objects.
 *
 * @package Schoolbox\Mail
 */
class TwigEmailFactory
{
    /**
     * Context used for building emails
     *
     * @var CoreContext
     */
    private $context;

    /** @var LoggerInterface */
    private $log;


    /**
     * Constructor
     *
     * @param CoreContext     $context
     * @param LoggerInterface $log
     */
    public function __construct(CoreContext $context, LoggerInterface $log)
    {
        $this->context = $context;
        $this->log     = $log;
    }


    /**
     * Builder
     *
     * @see TwigEmail::__construct()
     *
     * @param string $template
     * @param string $subject
     * @param string $to
     * @param string $host
     * @return TwigEmail
     */
    public function build($template, $subject = "", $to = "", $host = "")
    {
        $twigEmail = new TwigEmail($template, $subject, $to, $host);
        $twigEmail->setLogger($this->log);
        $twigEmail->setCoreContext($this->context->getContext());
        return $twigEmail;
    }


    /**
     * @return array
     */
    public function getCoreContext()
    {
        return $this->context->getContext();
    }
}
