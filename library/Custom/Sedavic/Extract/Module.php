<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Sedavic\Extract;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'sedavicExtract';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Only supers can use this
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $preloadDates = function ($params) {
            return $this->preloadDates($params);
        };

        $this->register('GET', '/{from}/{to}', 'getExtract', $getPerms, $preloadDates);
    }


    /**
     * Assert dates are sensible, i.e. $from is less than or equal to $to
     * with the following axioms:
     *
     * $from is always taken from start of day;
     * $to is always until the end of day
     *
     * @param array $params
     * @return array
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    private function preloadDates(array $params): array
    {
        /* Assert $from <= $to
         */
        $from = (new \Alaress_Date_Format($params['from'], \Alaress_Date_Format::DATABASE_DATE_FORMAT))->startOfDay();
        $to = (new \Alaress_Date_Format($params['to'], \Alaress_Date_Format::DATABASE_DATE_FORMAT))->endOfDay();

        if ($from->gt($to)) {
            throw new \UnexpectedValueException("Custom Extract: 'from' date must be before 'to' date");
        }

        return [
            'from' => $from,
            'to' => $to
        ];
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('databaseInternal'),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
    }
}
