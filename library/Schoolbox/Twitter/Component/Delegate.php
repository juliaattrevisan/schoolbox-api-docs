<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Twitter\Component;

use Component_ControllerAbstract;

/**
 * Delegate class for Twitter components. Handles the building of template
 * parameters, and whether the component will render an empty state.
 */
class Delegate
{
    private const TEMPLATE_TIMELINE     = '@twitter/variant/timeline.twig';
    private const TEMPLATE_TWEET        = '@twitter/variant/tweet.twig';
    private const HREF_PROFILE_TIMELINE = 'https://twitter.com/%s';


    /**
     * @var Component_ControllerAbstract
     */
    private $component;


    /**
     * Constructor
     *
     * @param Component_ControllerAbstract $component
     */
    public function __construct(Component_ControllerAbstract $component)
    {
        $this->component = $component;
    }


    /**
     * Which template should the component render?
     * * Single tweet: render the tweet variant
     * * Timeline of any type: render the timeline variant
     *
     * (If the URL contains the word "status" in its path, it's a tweet)
     *
     * @return string
     */
    public function determineTemplate(): string
    {
        $url  = $this->component->getConfig('url') ?? '';
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        return stripos($path, '/status/') === false
            ? self::TEMPLATE_TIMELINE
            : self::TEMPLATE_TWEET;
    }


    /**
     * Will this component render as an empty state?
     *
     * This controls whether the component will even show in some contexts.
     *
     * @return bool
     */
    public function isEmptyState(): bool
    {
        return ($this->component->getConfig('url') ?? '') === '';
    }


    /**
     * Build the params to send to the template.
     *
     * @return array
     */
    public function buildTemplateParams(): array
    {
        return [
            'id'          => $this->component->getInstance()->getId(),
            'href'        => $this->buildHref(),
            'componentId' => $this->component->getInstance()->getId(),
            'limit'       => $this->component->getConfig('size'),
        ];
    }


    /**
     * Build the href of the Twitter widget. This governs the content that is
     * loaded in the widget.
     *
     * @return string|null
     */
    private function buildHref(): ?string
    {
        // Put everything in a Twitter URL or username tag
        $url = $this->component->getConfig('url') ?? '';
        if ($url === '') {
            return null;
        }

        // We have a URL, but maybe it's actually a username
        $host = parse_url($url, PHP_URL_HOST);
        if (!isset($host)) {
            return sprintf(self::HREF_PROFILE_TIMELINE, $url);
        }

        if ($host !== 'twitter.com') {
            return null;
        }

        return $url;
    }
}
