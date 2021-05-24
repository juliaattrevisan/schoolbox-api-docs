<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Menu\Top\Mapper as TopMenuMapper;
use Schoolbox\User;

/**
 * Generate data for rendering the top menu.
 */
class TopMenu implements IDataGenerator
{
    /**
     * @var User|null
     */
    private $currentUser;


    /**
     * @var TopMenuMapper
     */
    private $mapper;


    /**
     * Constructor
     *
     * @param User|null     $currentUser
     * @param TopMenuMapper $mapper
     */
    public function __construct(?User $currentUser, TopMenuMapper $mapper)
    {
        $this->currentUser = $currentUser;
        $this->mapper      = $mapper;
    }


    /**
     * @inheritdoc
     */
    public function generate(?array $chromeData = []): array
    {
        try {
            $tabs = $this->mapper->findTabsForUser($this->currentUser);
        } catch (MapperException $e) {
            $tabs = [];
        }

        return [
            'tabCurrent' => $chromeData['tab'] ?? '',
            'tabs'       => $tabs,
            'user'       => $this->currentUser,
        ];
    }
}
