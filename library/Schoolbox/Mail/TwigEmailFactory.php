<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mail;

use Alaress\Lumberjack\Logger;
use Psr\Log\LoggerInterface;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Scrub;
use Symfony\Component\Mime\Address;
use TextFormatter;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

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
    private CoreContext $context;

    /** @var LoggerInterface */
    private LoggerInterface $log;

    private Environment $twig;

    /**
     * Constructor
     *
     * @param IConfigLoader   $config
     * @param LoggerInterface $log
     */
    public function __construct(IConfigLoader $config, ?LoggerInterface $log = null)
    {
        $this->context = new CoreContext($config);
        $this->log     = $log ?? new Logger(null);

        // load up paths in order of preference (start with school-specific)
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
    }


    /**
     * Builder
     *
     * @see TwigEmail::__construct()
     *
     * @param string $template
     * @param string $subject
     * @param string|array|Address|User $to
     * @return TwigEmail
     */
    public function build(string $template, string $subject, string|array|Address|User $to)
    {
        $twigEmail = new TwigEmail($template, $subject, $to);
        $twigEmail->setTwig($this->twig);
//        $twigEmail->setLogger($this->log);
        $twigEmail->setCoreContext($this->context->getContext());
//        $twigEmail->sign();
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
