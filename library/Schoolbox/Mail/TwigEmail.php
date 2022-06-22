<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Mail;

use Alaress\Mail\AbstractMailer;
use Schoolbox\Cdn\PrefixGenerator;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Alaress\Html\ToText as Html2Text;
use Scrub;
use TextFormatter;

/**
 * Class TwigEmail
 *
 * @package Schoolbox\Mail
 */
class TwigEmail extends AbstractMailer
{

    /**
     * @var array
     */
    protected array $coreContext = [];

    /**
     * @var CssToInlineStyles
     */
    protected CssToInlineStyles $cssInliner;

    /** @var Environment */
    protected Environment $twig;

    /** @var array */
    protected array $context = [];

    /** @var string */
    protected string $templateFilename;

    /** @var string */
    protected string $rendercache = '';

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

        $this->templateFilename = $template;

        // load up paths in order of preference (start with school-specific)
        $config = self::getConfig();
        $paths  = [];
        if (is_dir(($custom = SITE_root . '/templates/' . strtolower($config->get('site_school_code')) . '/email'))) {
            $paths[] = $custom;
        }
        $paths[] = SITE_root . '/templates/core/email';

        // Add options to Twig mailer too
        $options = [
            'debug'            => $config->get('debug_templates'),
            'strict_variables' => $config->get('strict_templates'),
        ];

        // do not cache when in debug mode
        if (!$config->get('debug_mode')) {
            $options['cache'] = $config->get('twig_cache_dir');
        }

        // load up a twig environment that will search for school-specific templates if the directory exists
        $this->twig = new Environment(new FilesystemLoader($paths), $options);

        //TODO: Refactor to use the Twig Environment from the bootstrap
        // HTML Purifier filter - known to output safe HTML
        $this->twig->addFilter(
            new TwigFilter(
                'purify',
                [Scrub::class, 'html'],
                ['is_safe' => ['html']]
            )
        );
        $this->twig->addFilter(
            new TwigFilter(
                'purifyAbsoluteUri',
                [Scrub::class, 'htmlAbsoluteUri'],
                ['is_safe' => ['html']]
            )
        );

        // Linkify filter
        $this->twig->addFilter(
            new TwigFilter(
                'linkify',
                [Scrub::class, 'linkifyText'],
                ['is_safe' => ['html']]
            )
        );

        // HTML2Text filter
        $this->twig->addFilter(
            new TwigFilter(
                'html2text',
                [Scrub::class, 'html2text']
            )
        );

        // Cut sentence filter
        $this->twig->addFilter(
            new TwigFilter(
                'cutSentence',
                [TextFormatter::class, 'cutSentence']
            )
        );

        // CDN_PREFIX
        $cdnPrefixGenerator = new PrefixGenerator($config);
        $this->twig->addGlobal('CDN_PREFIX', $cdnPrefixGenerator->generate());

        $this->cssInliner = new CssToInlineStyles();
    }

    /**
     * @param array $context
     * @return self
     */
    public function setContext($context)
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
    public function getContext()
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
    public function composeHTML()
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
    protected function composePlain()
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
        $this->setCoreContext(['config' => ['subject' => $subject]]);
    }
}
