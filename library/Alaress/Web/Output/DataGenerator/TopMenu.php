<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Alaress\Web\Output\ThemedHtml;
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
    public function generate(ThemedHtml $output): array
    {
        try {
            $tabs = $this->mapper->findTabsForUser($this->currentUser);
        } catch (MapperException $e) {
            $tabs = [];
        }

        return [
            'tabCurrent' => $output->getTab(),
            'tabs'       => $tabs,
            'user'       => $this->currentUser,
        ];
    }
}
