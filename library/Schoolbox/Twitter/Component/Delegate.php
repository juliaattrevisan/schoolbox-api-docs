<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Twitter\Component;

use Component_ControllerAbstract;

/**
 * Delegate class for Twitter components
 */
class Delegate
{
    public const TEMPLATE                  = '@twitter/component.twig';
    private const HREF_PROFILE_TIMELINE    = 'https://twitter.com/%s';
    private const HREF_LIST_TIMELINE       = 'https://twitter.com/%s/lists/%s';
    private const HREF_COLLECTION_TIMELINE = 'https://twitter.com/%s/timelines/%d';


    /**
     * Build the params to send to the template.
     *
     * @param Component_ControllerAbstract $component
     * @return array
     */
    public function buildTemplateParams(Component_ControllerAbstract $component): array
    {
        return [
            'href'         => $this->buildHref($component),
            'componentId'  => $component->getInstance()->getId(),
            'limit'        => $component->getConfig('size'),
        ];
    }


    /**
     * Build the href of the Twitter widget. This governs the content that is
     * loaded in the widget.
     *
     * @param Component_ControllerAbstract $component
     * @return string|null
     */
    private function buildHref(Component_ControllerAbstract $component): ?string
    {
        $username = $component->getConfig('username') ?? '';
        if ($username === '') {
            return null;
        }

        $listSlug = $component->getConfig('twitterListSlug') ?? '';
        if ((string)$listSlug !== '') {
            return sprintf(self::HREF_LIST_TIMELINE, $username, $listSlug);
        }

        $collectionId = $component->getConfig('twitterCollectionId') ?? '';
        if ((string)$collectionId !== '') {
            return sprintf(self::HREF_COLLECTION_TIMELINE, $username, $collectionId);
        }

        return sprintf(self::HREF_PROFILE_TIMELINE, $username);
    }
}
