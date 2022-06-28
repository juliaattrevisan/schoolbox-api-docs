<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Mail;

use Alaress\Mail\Mailer;
use Schoolbox\Admin\EmailDKIM\Mapper;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Symfony\Component\Mime\Address;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use Twig\Environment;
use Alaress\Html\ToText as Html2Text;

/**
 * Class TwigEmail
 *
 * @package Schoolbox\Mail
 */
class TwigEmail extends Mailer
{
    /**
     * @var array
     */
    protected array $coreContext = [];

    /**
     * @var CssToInlineStyles
     */
    protected CssToInlineStyles $cssInliner;

    /** @var Environment|null */
    protected ?Environment $twig = null;

    /** @var array */
    protected array $context = [];

    /** @var string */
    protected string $templateFilename;

    /** @var string */
    protected string $rendercache = '';

    /**
     * DO NOT DIRECTLY INSTANTIATE THIS - use EmailFactory (di->get('mail.emailFactory')) instead
     * Prepares the email by importing various configurations from the system
     *
     * @param string                    $subject - the default email subject, can be changed after construction
     * @param string|array|Address|User $to      - the defailt recipient, can be changed after construction
     * @param IConfigLoader             $config
     * @param Mapper                    $dkimMapper
     */
    public function __construct(string $subject, string | array | Address | User $to, IConfigLoader $config, Mapper $dkimMapper)
    {
        parent::__construct($subject, $to, $config, $dkimMapper);

        $this->cssInliner = new CssToInlineStyles();
    }

    /**
     * @param string $template
     * @return void
     */
    public function setTemplate(string $template)
    {
        $this->templateFilename = $template;
    }

    /**
     * @param Environment $twig
     * @return void
     */
    public function setTwig(Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * @param array $context
     * @return self
     */
    public function setContext(array $context): self
    {
        if ($context) {
            $this->context = array_merge_recursive($this->context, $context);
        } else {
            $this->context = [];
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }


    /**
     * Use both $coreContext and $context to compose HTML
     *
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function composeHTML(): string
    {
        $preformat         = $this->twig->render(
            $this->templateFilename,
            array_replace_recursive($this->coreContext, $this->context)
        );
        $this->rendercache = $this->cssInliner->convert($preformat);
        // truncate all sequential whitespace as well as whitespace between tags (will probably result in one line of HTML -_-;)
        $this->rendercache = preg_replace(['`\s\s*`', '`>\s+<`'], [' ', '><'], $this->rendercache);
        return $this->rendercache;
    }

    /**
     * Returns the plaintext rendering of the templated email constructed by this object.
     *
     * @return string
     */
    protected function composePlain(): string
    {
        if ($this->rendercache) {
            $converter = new Html2Text($this->rendercache);
            return $converter->getText();
        }
        $converter = new Html2Text($this->composeHTML());
        return $converter->getText();
    }

    /**
     * @param array $coreContext
     */
    public function setCoreContext(array $coreContext)
    {
        $this->coreContext = array_replace_recursive($this->coreContext, $coreContext);
    }


    /**
     * Return the configuration elements of config for this email
     *
     * @return array
     */
    public function getCoreContext(): array
    {
        return $this->coreContext;
    }


    /**
     * Set the subject, both in the email header and the config
     *
     * @param string $subject
     */
    public function setSubject(string $subject)
    {
        parent::setSubject($subject);
        $this->setCoreContext(['config' => ['subject' => $subject]]);
    }
}
