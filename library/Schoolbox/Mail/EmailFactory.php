<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Mail;

use Alaress\Lumberjack\Logger;
use Alaress\Mail\Mailer;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\EmailDKIM\Mapper as DKIMMapper;
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
 * Builder for email objects.
 *
 * @package Schoolbox\Mail
 */
class EmailFactory
{
    /**
     * Context used for building emails
     *
     * @var CoreContext
     */
    private CoreContext $context;

    /** @var LoggerInterface */
    private LoggerInterface $log;

    private ?Environment $twig = null;

    private IConfigLoader $config;

    private DKIMMapper $dkimMapper;

    /**
     * Constructor
     *
     * @param IConfigLoader        $config
     * @param DKIMMapper           $dkimMapper
     * @param LoggerInterface|null $log
     */
    public function __construct(IConfigLoader $config, DKIMMapper $dkimMapper, ?LoggerInterface $log = null)
    {
        $this->config     = $config;
        $this->log        = $log ?? new Logger(null);
        $this->dkimMapper = $dkimMapper;
    }

    /**
     * Builder
     *
     * @param string                    $template
     * @param string                    $subject
     * @param string|array|Address|User $to
     * @return TwigEmail
     * @see TwigEmail::__construct()
     *
     */
    public function buildTwigEmail(string $template, string $subject, string | array | Address | User $to): TwigEmail
    {
        $twigEmail = new TwigEmail($subject, $to, $this->config, $this->dkimMapper);
        $twigEmail->setTemplate($template);
        $twigEmail->setTwig($this->getTwig());
        $twigEmail->setLogger($this->log);
        $twigEmail->setCoreContext($this->context->getContext());
        return $twigEmail;
    }

    public function buildSimpleEmail(string $subject, string | array | Address | User $to): Mailer
    {
        $email = new Mailer($subject, $to, $this->config, $this->dkimMapper);
        $email->setLogger($this->log);
        return $email;
    }

    /**
     * @return array
     */
    public function getCoreContext(): array
    {
        return $this->context->getContext();
    }

    /**
     * @return Environment
     */
    private function getTwig(): Environment
    {
        if ($this->twig) {
            return $this->twig;
        }
        $this->context = new CoreContext($this->config);

        // load up paths in order of preference (start with school-specific)
        $paths = [];
        if (is_dir(($custom = SITE_root . '/templates/' . strtolower($this->config->get('site_school_code')) . '/email'))) {
            $paths[] = $custom;
        }
        $paths[] = SITE_root . '/templates/core/email';

        // Add options to Twig mailer too
        $options = [
            'debug'            => $this->config->get('debug_templates'),
            'strict_variables' => $this->config->get('strict_templates'),
        ];

        // do not cache when in debug mode
        if (!$this->config->get('debug_mode')) {
            $options['cache'] = $this->config->get('twig_cache_dir');
        }

        // load up a twig environment that will search for school-specific templates if the directory exists
        $twig = new Environment(new FilesystemLoader($paths), $options);

        //TODO: Refactor to use the Twig Environment from the bootstrap
        // HTML Purifier filter - known to output safe HTML
        $twig->addFilter(
            new TwigFilter(
                'purify',
                [Scrub::class, 'html'],
                ['is_safe' => ['html']]
            )
        );
        $twig->addFilter(
            new TwigFilter(
                'purifyAbsoluteUri',
                [Scrub::class, 'htmlAbsoluteUri'],
                ['is_safe' => ['html']]
            )
        );

        // Linkify filter
        $twig->addFilter(
            new TwigFilter(
                'linkify',
                [Scrub::class, 'linkifyText'],
                ['is_safe' => ['html']]
            )
        );

        // HTML2Text filter
        $twig->addFilter(
            new TwigFilter(
                'html2text',
                [Scrub::class, 'html2text']
            )
        );

        // Cut sentence filter
        $twig->addFilter(
            new TwigFilter(
                'cutSentence',
                [TextFormatter::class, 'cutSentence']
            )
        );

        // CDN_PREFIX
        $cdnPrefixGenerator = new PrefixGenerator($this->config);
        $twig->addGlobal('CDN_PREFIX', $cdnPrefixGenerator->generate());
        $this->twig = $twig;
        return $this->twig;
    }
}
