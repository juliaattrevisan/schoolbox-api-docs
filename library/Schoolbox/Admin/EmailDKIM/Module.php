<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\EmailDKIM;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule\All;
use Alaress\Authorizer\Rule\IsConfigOn;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper as BaseMapper;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'emailDKIM';

    /**
     * @inheritDoc
     */
    public function registerRoutes(): void
    {
        $perm             = new All(
            new IsLoggedIn(),
            new IsSuperuser(),
            new IsConfigOn(ConfigMapper::NAME_MAIL_ENABLED, $this->getConfig())
        );
        $preloadEmailDKIM = function (array $params) {
            return $this->preloadEmailDKIM($params);
        };

        $this->register(self::METHOD_GET, '', 'getDomainListPage', $perm);
        $this->register(self::METHOD_GET, '/list', 'getDomainJsonList', $perm);
        $this->register(self::METHOD_GET, '/domain/{emailDKIMId:\d+}', 'getDKIMJson', $perm, $preloadEmailDKIM);
        $this->register(self::METHOD_POST, '/domain', 'postDomain', $perm);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getEmailDKIMMapper()

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
     * @param array $params
     * @return array
     * @throws BaseMapper\DefinitionException
     * @throws InvalidPath
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    private function preloadEmailDKIM(array $params): array
    {
        if (($params['emailDKIM'] ?? null) instanceof Model) {
            $preload['emailDKIM'] = $params['emailDKIM'];
        } elseif (!\array_key_exists('emailDKIMId', $params)) {
            throw new \UnexpectedValueException("No 'emailDKIMId' found in route params");
        } else {
            try {
                $preload['emailDKIM'] = $this->getEmailDKIMMapper()->findById($params['emailDKIMId']);
            } catch (BaseMapper\Exception $e) {
                throw new InvalidPath('EmailDKIM Not Found', 0, $e);
            }
        }
        return $preload;
    }

    /**
     * @return Mapper
     * @throws \InvalidArgumentException
     */
    private function getEmailDKIMMapper(): Mapper
    {
        /** @var Mapper $mapper * */
        $mapper = $this->getMapperFactory()->get(Mapper::class);

        return $mapper;
    }
}
