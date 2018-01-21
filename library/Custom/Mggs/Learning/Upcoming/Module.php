<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Mggs\Learning\Upcoming;

use Alaress\Authorizer\Rule\IsLoggedIn;
use Aura\Di\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
use Twig_Loader_Chain;


/**
 * Overload the existing module as-is, and just change our templates directory
 *
 * @package Custom\Mggs\Learning\Upcoming
 */
class Module extends \Schoolbox\Learning\Upcoming\Module
{
    /**
     * @inheritdoc
     */
    public function registerRoutes()
    {
        parent::registerRoutes();

        $this->register('GET', '/terms/{date}', 'getTermDates', new IsLoggedIn(), function (array $params) {

            try {
                return [
                    'now' => \Alaress_Date_Format::newUrlDate($params['date'])
                ];
            } catch (\InvalidArgumentException $e) {
                throw new NotFound();
            }

        });
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        // Use this custom module's templates in preference to those defined by the default module
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates', $this->getBasename() . 'Mggs');
        parent::initTemplatePaths($templateLoader);
    }
}
