<?php

namespace Schoolbox\Admin\HealthCheck;

use Alaress\Web\Controller\Base;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\MessageFactory;
use Schoolbox\User\Translator\Author\ModelToArray;
use Scrub;

class Controller extends Base
{
    /**
     * Max length for filenames: anything longer causes issues on upload.
     */
    private const FILENAME_MAX_LENGTH = 250;
    private Jwt $tokenBuilder;

    /**
     * Constructor
     *
     * @param Jwt $tokenBuilder
     */
    public function __construct(
        Jwt $tokenBuilder
    ) {
        $this->tokenBuilder = $tokenBuilder;
    }

    public function getAudit()
    {
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();
        $reports = [];
        $reports[] = [
            'filename' => sprintf(
                "%s/%s.csv",
                'assessmentExport36',
                mb_substr('assessmentExport', 0, self::FILENAME_MAX_LENGTH, 'UTF-8'),
            ),
            'source'     => $urlBase->setPath('/learning/assessment-audit/0')->toString(),
        ];

        $escapedTitle = Scrub::filename(trim('exportTest') ?? '');
        // Get the taskd context, and send
        /** @var Producer $producer */
        $producer = $this->getDi()->get('taskd.messageProducer');

        /** @var MessageFactory $messageFactory */
        $messageFactory = $this->getDi()->get('taskd.messageFactory');
        $userSerializer = new ModelToArray();

        $date = \Alaress_Date_Format::now()->getDatabaseDate();
        $outfile = $escapedTitle !== ''
            ? ($date . '-' . $escapedTitle)
            : $date;
        $message = $messageFactory->build(
            'download-pages',
            [
                'token'     => $this->tokenBuilder->generate($this->getUser()),
                'content'   => $reports,
                'outfile'   => mb_substr($outfile, 0, self::FILENAME_MAX_LENGTH, 'UTF-8') . '.zip',
                'gotenberg' => [
                    'url' => $this->getConfigLoader()->get(ConfigMapper::NAME_GOTENBERG_HOST),
                    'key' => $this->getConfigLoader()->get(ConfigMapper::NAME_GOTENBERG_API_KEY),
                ],
            ],
            $urlBase->setPath($this->getRouter()->generate('apiusernotify.apiUserNotify', ['userId' => $this->getUser()->getId()]))
                    ->setQuery(null)
                    ->toString(),
            [
                'message' => sprintf('Your Healthcheck %s is ready to download', $this->getConfigLoader()->get(ConfigMapper::NAME_REPORT_TITLE)),
                'from'    => call_user_func($userSerializer, $this->getUser()),
                'to'      => call_user_func($userSerializer, $this->getUser()),
            ],
            $this->getUser(),
        );

        $producer->send($message);

        //redirect home
        $this->getResponse()->redirect->found('/');
    }
}
