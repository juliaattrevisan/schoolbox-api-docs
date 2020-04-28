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
    private const TEMPLATE_EMPTY               = '@twitter/component/empty.twig';
    private const TEMPLATE_PROFILE_TIMELINE    = '@twitter/component/timeline/profile.twig';
    private const TEMPLATE_LIST_TIMELINE       = '@twitter/component/timeline/list.twig';
    private const TEMPLATE_COLLECTION_TIMELINE = '@twitter/component/timeline/collection.twig';


    /**
     * Which template should the component render?
     * * No username: render the empty state
     * * Username only: render the profile timeline
     * * Username + list slug: render the list timeline
     * * Username + collection ID: render the collection timeline
     *
     * @param Component_ControllerAbstract $component
     * @return string
     */
    public function determineTemplate(Component_ControllerAbstract $component): string
    {
        $username = $component->getConfig('username') ?? '';
        if ($username === '') {
            return self::TEMPLATE_EMPTY;
        }

        $listSlug = $component->getConfig('listSlug') ?? '';
        if ($listSlug !== '') {
            return self::TEMPLATE_LIST_TIMELINE;
        }

        $collectionId = $component->getConfig('collectionId') ?? '';
        if ($collectionId !== '') {
            return self::TEMPLATE_COLLECTION_TIMELINE;
        }

        return self::TEMPLATE_PROFILE_TIMELINE;
    }


    /**
     * Which params should be sent to the template?
     *
     * @param Component_ControllerAbstract $component
     * @return array
     */
    public function buildTemplateParams(Component_ControllerAbstract $component): array
    {
        return [
            'componentId'  => $component->getInstance()->getId(),
            'username'     => $component->getConfig('username'),
            'keywords'     => $component->getConfig('keywords'),
            'listSlug'     => $component->getConfig('listSlug'),
            'collectionId' => $component->getConfig('collectionId'),
            'limit'        => $component->getConfig('limit'),
        ];
    }
}
