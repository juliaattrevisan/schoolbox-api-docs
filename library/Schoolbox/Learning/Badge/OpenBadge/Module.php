<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\OpenBadge;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Learning\Badge\Mapper as BadgeMapper;
use Schoolbox\Learning\Badge\Award\Mapper as AwardMapper;

use Closure;

/**
 * Module for badge requests.
 */
class Module extends BaseModule
{
    protected $basename = 'learningBadgeApi';

    public function registerRoutes(): void
    {
        /* Get a JSON file containing issuer information
         */
        $this->register(
            self::METHOD_GET,
            '/issuer.json',
            'getIssuerJson'
        );

        $this->register(
            self::METHOD_GET,
            '/badge/{badgeId}.json',
            'getBadgeJson',
            null,
            Closure::fromCallable([$this, 'preloadBadge']),
        );

        $this->register(
            self::METHOD_GET,
            '/assertion/{awardId}.json',
            'getBadgeAssertion',
            null,
            Closure::fromCallable([$this, 'preloadAward']),
        );
    }


    /**
     * Preload the requested badge
     *
     * @param array $params
     * @return array
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function preloadBadge(array $params): array
    {
        return [
            'badge' => $this->getMapperFactory()
                            ->get(BadgeMapper::class)
                            ->findById($params['badgeId']),
        ];
    }

    /**
     * Preload the requested awarded badge (assertion)
     *
     * @param array $params
     * @return array
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function preloadAward(array $params): array
    {
        return [
            'award' => $this->getMapperFactory()
                            ->get(AwardMapper::class)
                            ->findById($params['awardId']),
        ];
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            new PrefixGenerator($this->getConfig()),
        );
    }
}
