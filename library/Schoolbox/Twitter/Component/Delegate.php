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
    public const  TEMPLATE                 = '@twitter/component.twig';
    private const HREF_PROFILE_TIMELINE    = 'https://twitter.com/%s';
    private const HREF_LIST_TIMELINE       = 'https://twitter.com/%s/lists/%s';
    private const HREF_COLLECTION_TIMELINE = 'https://twitter.com/%s/timelines/%d';


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
     * Build the params to send to the template.
     *
     * @return array
     */
    public function buildTemplateParams(): array
    {
        return [
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
        $username = $this->component->getConfig('username') ?? '';
        if ($username === '') {
            return null;
        }

        $listSlug = $this->component->getConfig('twitterListSlug') ?? '';
        if ((string)$listSlug !== '') {
            return sprintf(self::HREF_LIST_TIMELINE, $username, $listSlug);
        }

        $collectionId = $this->component->getConfig('twitterCollectionId') ?? '';
        if ((string)$collectionId !== '') {
            return sprintf(self::HREF_COLLECTION_TIMELINE, $username, $collectionId);
        }

        return sprintf(self::HREF_PROFILE_TIMELINE, $username);
    }
}
