<?php

 /**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Mail;

use Alaress\Mail\TwigMailer;
use Schoolbox\UserMapper;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Class TwigEmail
 *
 * @package Schoolbox\Mail
 */
class TwigEmail extends TwigMailer
{

    /**
     * @var array
     */
    protected $coreContext = array();

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var CssToInlineStyles
     */
    protected $cssInliner;

    /**
     * Prepares the email by importing various configurations from the system
     *
     * @param string $template - filename of the Twig template, relative to 'templates/[core|site]/email'
     * @param string $subject  - the default email subject, can be changed after construction
     * @param string $to       - the defailt recipient, can be changed after construction
     * @param string $host     - deprecated, does nothing
     */
    public function __construct($template, $subject = "", $to = "", $host = "")
    {
        parent::__construct($template, $subject, $to, $host);

        $this->userMapper = UserMapper::getInstance();
        $this->cssInliner = new CssToInlineStyles();
    }


    /**
     * Use both $coreContext and $context to compose HTML
     *
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function composeHTML()
    {
        $preformat = $this->twig->render(
            $this->templateFilename,
            array_replace_recursive($this->coreContext, $this->context)
        );
        $this->rendercache = $this->cssInliner->convert($preformat);
        // truncate all sequential whitespace as well as whitespace between tags (will probably result in one line of HTML -_-;)
        $this->rendercache = preg_replace([ '`\s\s*`', '`>\s+<`' ], [ ' ', '><' ], $this->rendercache);
        return $this->rendercache;
    }


    /**
     * @param array $coreContext
     */
    public function setCoreContext($coreContext)
    {
        $this->coreContext = array_replace_recursive($this->coreContext, $coreContext);
    }


    /**
     * Return the configuration elements of config for this email
     *
     * @return array
     */
    public function getCoreContext()
    {
        return $this->coreContext;
    }


    /**
     * Set the subject, both in the email header and the config
     *
     * @param string $subject
     */
    public function setSubject($subject)
    {
        parent::setSubject($subject);
        $this->setCoreContext([ 'config' => [ 'subject' => $subject ] ]);
    }
}
