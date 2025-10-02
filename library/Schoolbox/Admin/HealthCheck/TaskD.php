<?php

namespace Schoolbox\Admin\HealthCheck;

use Aura\Router\Generator;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Gotenberg\Client;
use Schoolbox\IConfigLoader;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\MessageFactory;
use Schoolbox\Url\Uri;
use Schoolbox\User;
use Schoolbox\User\Token\Mapper as TokenMapper;
use Schoolbox\User\Translator\Author\ModelToArray;
use Scrub;

class TaskD
{
    /**
     * Max length for filenames: anything longer causes issues on upload.
     */
    private const FILENAME_MAX_LENGTH = 250;

    private Uri $urlBase;

    private array $sources;

    /**
     * Constructor
     *
     * @param Jwt $tokenBuilder
     * @param IConfigLoader $config
     * @param Generator $routeGenerator
     * @param MessageFactory $messageFactory
     * @param Producer $producer
     * @param TokenMapper $tokenMapper
     */
    public function __construct(
        private Jwt $tokenBuilder,
        private IConfigLoader $config,
        private Generator $routeGenerator,
        private MessageFactory $messageFactory,
        private Producer $producer,
        private TokenMapper $tokenMapper,
    ) {
        $this->urlBase = (new \Schoolbox\Url\Factory($config))->buildFullyQualified();
    }

    /**
     * @param string     $filename
     * @param User       $user
     * @param array      $sources
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     */
    public function sendToTaskD(string $filename, User $user, array $sources = [])
    {
        //if passed sources is empty, grab class saved
        if (empty($sources)) {
            $sources = $this->sources;
        }
        $escapedTitle = Scrub::filename(trim($filename));
        // Get the taskd context, and send

        $userSerializer = new ModelToArray();

        $date = \Alaress_Date_Format::now()->getDatabaseDate();
        $outfile = $escapedTitle !== ''
            ? ($date . '-' . $escapedTitle)
            : $date;
        $token = $this->tokenMapper->generateForUser($user, $user, \Alaress_Date_Format::now()->addSeconds(JWT::API_JWT_TIMEOUT));
        $message = $this->messageFactory->build(
            'download-pages',
            [
                'token'     => $this->tokenBuilder->generate($user, $token->getJwtId(), $token->getExpiry()),
                'content'   => $sources,
                'outfile'   => mb_substr($outfile, 0, self::FILENAME_MAX_LENGTH, 'UTF-8') . '.zip',
                'gotenberg' => [
                    'url'     => $this->config->get(ConfigMapper::NAME_GOTENBERG_HOST),
                    'auth'    => [
                        'key'    => $this->config->get(ConfigMapper::NAME_GOTENBERG_API_KEY),
                        'header' => Client::API_KEY_HEADER,
                    ],
                ],
            ],
            (string)$this->urlBase->withPath($this->routeGenerator->generate('apiusernotify.apiUserNotify', ['userId' => $user->getId()]))
                    ->appendQueryParameters([]),
            [
                'message' => sprintf('Your Healthcheck %s is ready to download', $this->config->get(ConfigMapper::NAME_REPORT_TITLE)),
                'from'    => call_user_func($userSerializer, $user),
                'to'      => call_user_func($userSerializer, $user),
            ],
            null,
            null,
            $user,
        );

        $this->producer->send($message);
    }

    public function addSource(string $folder, string $report, ?string $url = null)
    {
        //helper URL object
        $urlBase = (new \Schoolbox\Url\Factory($this->config))->buildFullyQualified();

        //if passed url is null, generate a local url
        if (null === $url) {
            $url = $urlBase->withPath($this->routeGenerator->generate('adminHealthCheckExport.' . $report));
        }
        $this->sources[] = [
            'filename' => sprintf(
                "%s/%s.csv",
                $folder,
                mb_substr($report, 0, self::FILENAME_MAX_LENGTH, 'UTF-8'),
            ),
            'source' => $url,
        ];
    }
}
