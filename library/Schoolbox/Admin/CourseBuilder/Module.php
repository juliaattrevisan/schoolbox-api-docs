<?php

/**
 * Course builder admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\CourseBuilder;

use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Assessment\Course\Template\Mapper as CourseTemplateMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Mapper as BaseMapper;

class Module extends BaseModule
{
    protected $basename = 'adminCourseBuilder';

    protected function registerRoutes(): void
    {
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL)
            )
        );

        $loadTemplate = function (array $params) {
            return $this->preloadTemplate($params);
        };

        $this->register('GET', "", 'getIndex', $perms);
        $this->register('POST', "/save", 'postSave', $perms);
        $this->register('DELETE', "/delete/{templateId:\d+}", 'deleteTmpl', $perms, $loadTemplate);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ConfigMapper::class),
            $this->getMapperFactory()->get(CourseConfigMapper::class),
            $this->getMapperFactory()->get(HomepageTemplateMapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getCourseTemplateMapper(),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @return CourseTemplateMapper
     */
    private function getCourseTemplateMapper(): CourseTemplateMapper
    {
        return $this->getMapperFactory()->get(CourseTemplateMapper::class);
    }

    /**
     * @param array $params
     * @return array
     */
    private function preloadTemplate(array $params): array
    {
        if (!\array_key_exists('templateId', $params)) {
            throw new \UnexpectedValueException("No 'templateId' found in route params");
        } else {
            try {
                $preload['template'] = $this->getCourseTemplateMapper()->findById($params['templateId']);
            } catch (BaseMapper\Exception $e) {
                throw new InvalidPath('Course Template Not Found', 0, $e);
            }
        }
        return $preload;
    }
}
