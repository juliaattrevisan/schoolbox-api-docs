<?php

namespace Schoolbox\Admin\HealthCheck;

use Aura\Router\Router;
use Laminas\Uri\UriInterface;
use Psr\Container\ContainerInterface;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\MessageFactory;
use Schoolbox\User;
use Schoolbox\User\Translator\Author\ModelToArray;
use Scrub;

class Helper
{
    /**
     * Max length for filenames: anything longer causes issues on upload.
     */
    private const FILENAME_MAX_LENGTH = 250;
    private Jwt $tokenBuilder;
    private IConfigLoader $config;
    private Router $router;
    private UriInterface $urlBase;
    private MessageFactory $messageFactory;
    private Producer $producer;

    /**
     * Constructor
     *
     * @param Jwt $tokenBuilder
     */
    public function __construct(
        Jwt $tokenBuilder,
        IConfigLoader $config,
        Router $router,
        MessageFactory $messageFactory,
        Producer $producer
    ) {
        $this->tokenBuilder = $tokenBuilder;
        $this->config = $config;
        $this->router = $router;
        $this->urlBase = (new \Schoolbox\Url\Factory($config))->buildFullyQualified();
        $this->messageFactory = $messageFactory;
        $this->producer = $producer;
    }


    public function sendToTaskD(string $filename, array $files, User $user)
    {
        $escapedTitle = Scrub::filename(trim($filename));
        // Get the taskd context, and send

        $userSerializer = new ModelToArray();

        $date = \Alaress_Date_Format::now()->getDatabaseDate();
        $outfile = $escapedTitle !== ''
            ? ($date . '-' . $escapedTitle)
            : $date;
        $message = $this->messageFactory->build(
            'download-pages',
            [
                'token'     => $this->tokenBuilder->generate($user),
                'content'   => $files,
                'outfile'   => mb_substr($outfile, 0, self::FILENAME_MAX_LENGTH, 'UTF-8') . '.zip',
                'gotenberg' => [
                    'url' => $this->config->get(ConfigMapper::NAME_GOTENBERG_HOST),
                    'key' => $this->config->get(ConfigMapper::NAME_GOTENBERG_API_KEY),
                ],
            ],
            $this->urlBase->setPath($this->router->generate('apiusernotify.apiUserNotify', ['userId' => $user->getId()]))
                    ->setQuery(null)
                    ->toString(),
            [
                'message' => sprintf('Your Healthcheck %s is ready to download', $this->config->get(ConfigMapper::NAME_REPORT_TITLE)),
                'from'    => call_user_func($userSerializer, $user),
                'to'      => call_user_func($userSerializer, $user),
            ],
            $user,
        );

        $this->producer->send($message);
    }
}
