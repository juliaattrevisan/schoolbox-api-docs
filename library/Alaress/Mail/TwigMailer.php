<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Mail;

use Alaress\Html\ToText as Html2Text;
use Schoolbox\Cdn\PrefixGenerator;
use Scrub;
use TextFormatter;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

class TwigMailer extends AbstractMailer
{

    /** @var Environment */
    protected $twig;

    /** @var array */
    protected $context = [];

    /** @var string */
    protected $templateFilename;

    /** @var string */
    protected $rendercache = '';

    /**
     * @param string $template
     * @param string $subject
     * @param string $to
     * @param string $host
     */
    public function __construct($template, $subject = "", $to = "", $host = "")
    {
        parent::__construct($template, $subject, $to, $host);

        $this->templateFilename = $template;

        // load up paths in order of preference (start with school-specific)
        $config = self::getConfig();
        $paths  = [];
        if (is_dir(($custom = SITE_root . '/templates/' . $config->get('site_location') . '/email'))) {
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
     * Returns the HTML rendering of the templated email constructed by this object.
     *
     * @return string
     */
    protected function composeHTML()
    {
        return $this->rendercache = $this->twig->render($this->templateFilename, $this->context);
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
}
