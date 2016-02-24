<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mail;

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
    protected $context;

    /**
     * Constructor
     *
     * @param CoreContext $context
     */
    public function __construct(CoreContext $context)
    {
        $this->context = $context;
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
     *
     * @return TwigEmail
     */
    public function build($template, $subject = "", $to = "", $host = "")
    {
        $twigEmail = new TwigEmail($template, $subject, $to, $host);
        $twigEmail->setCoreContext($this->context->getContext());
        return $twigEmail;
    }
}
