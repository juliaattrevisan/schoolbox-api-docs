<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Download;

use Alaress_Date_Format;
use Aura\Router\Router;
use Laminas\Uri\UriInterface;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Submission;
use Schoolbox\TaskDaemon\Request\DownloadPages\MessageFactory as BaseMessageFactory;
use Schoolbox\TaskDaemon\Request\Message;
use Schoolbox\User;
use Schoolbox\User\Translator\Author\ModelToArray;

/**
 * Message factory for due work ZIP downloads.
 *
 * This builds a message to be sent to the task daemon: this message requests
 * that all the various pages and downloads which constitute a due work download
 * be included in a ZIP file which is generated and returned to the requesting
 * user.
 */
class MessageFactory
{
    /**
     * Constructor
     *
     * @param BaseMessageFactory $baseMessageFactory Required for actually building messages
     * @param UriInterface       $urlBase            Required for building URIs which point back to this instance
     * @param RosettaStone       $rosettaStone       Required for translating the term "due work" for this instance
     * @param Router             $router             Required for building routes to download various parts of due work
     */
    public function __construct(
        private BaseMessageFactory $baseMessageFactory,
        private UriInterface $urlBase,
        private RosettaStone $rosettaStone,
        private Router $router,
    ) {
    }

    /**
     * Builds a message to send to the task daemon.
     *
     * The message is a request to include the following files in a ZIP:
     * * Each participant's submission history
     * * All files in each participant's responses to the due work
     * * All Kami feedback for each participant
     *
     * @param DueWork $dueWork The due work to build a message for.
     * @param User    $viewer  The user who is requesting the due work ZIP.
     * @return Message
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function buildForAssessment(DueWork $dueWork, User $viewer): Message
    {
        /** @var User[] $users */
        $users = [];

        // Generate list of files to fetch via taskd, and add to a ZIP
        $messageFactory = (clone $this->baseMessageFactory);
        $userSerializer = new ModelToArray();

        // Iterate participants and get their responses
        foreach ($dueWork->getAllParticipants() as $participant) {
            // Short-circuit if there's no interactions
            // (draft feedback does count, though)
            if (!$participant->hasSubmittedResponse() && $participant->getAllFeedbacks()->count() === 0) {
                continue;
            }

            // Get the users details and generate an appropriate folder name
//            $users[$participant->getUser()->getId()]
//                ??= $this->getUserMapper()->findByIdWithFallback($participant->getUser()->getId());
            $participantUser = $participant->getUser();
            $baseFolderName  = sprintf(
                "%s_%s_%d",
                $this->sanitizeForFilename($participantUser->getLastname()),
                $this->sanitizeForFilename($participantUser->getFirstname()),
                $participantUser->getId(),
            );

            // Iterate all their submitted responses
            /** @var Submission $response */
            foreach ($participant->getSubmittedResponses() as $response) {
                // If the response is a submission...
                if ($response instanceof Submission) {
                    // ...iterate all files in that submission and add them to
                    // the ZIP archive downloaded from taskd
                    foreach ($response->getAllFiles() as $file) {
                        $messageFactory = $messageFactory->addFileContent(
                            sprintf(
                                "%s/%s/%s",
                                $baseFolderName,
                                $response->getSubmittedAt()->getUsingFormat('Ymd-His'),
                                $this->sanitizeForFilename($file->getBasename())
                            ),
                            $this->urlBase
                                ->setPath('/storage/fetch.php')
                                ->setQuery(['hash' => $file->getHash()])
                                ->toString(),
                        );
                        if ($file->getKami()) {
                            $messageFactory = $messageFactory->addFileContent(
                                sprintf(
                                    "%s/%s/%s",
                                    $baseFolderName,
                                    $response->getSubmittedAt()->getUsingFormat('Ymd-His'),
                                    $this->sanitizeForFilename($file->getBasename() . '.kami.pdf')
                                ),
                                $this->urlBase
                                    ->setPath(
                                        $this->router->generate(
                                            'learningDueWorkResponse.getKamiExport',
                                            [
                                                'kamiId' => $file->getKami()->getId(),
                                            ],
                                        )
                                    )
                                    ->toString(),
                            );
                        }
                    }
                }
            }

            // Add this participant's submission history
            $messageFactory = $messageFactory->addPdfContent(
                sprintf(
                    "%s/submissionHistory.pdf",
                    $baseFolderName,
                ),
                $this->urlBase
                    ->setPath(
                        $this->router->generate(
                            'learningAssessment.getSummary',
                            [
                                'assessmentId' => $participant->getAssessable()->getId(),
                                'studentId'    => $participant->getUser()->getId(),
                            ],
                        )
                    )
                    ->toString(),
            );
        }

        // Send the entire request to taskd
        $title = sprintf(
            "%s-%s.zip",
            $this->sanitizeForFilename($dueWork->getTitle()),
            Alaress_Date_Format::now()->getUsingFormat('Ymd-His'),
        );
        return $messageFactory
            ->setUser($viewer)
            ->setCallbackUrl(
                $this->urlBase
                    ->setPath(
                        $this->router->generate(
                            'apiusernotify.apiUserNotify',
                            ['userId' => $viewer->getId()]
                        )
                    )
                    ->setQuery(null)
                    ->toString()
            )
            ->setCallbackParams([
                                    'message' => sprintf(
                                        'Feedback and submissions for %s "%s" are ready to download',
                                        $this->rosettaStone->translate('dueWork'),
                                        $title,
                                    ),
                                    'from'    => call_user_func($userSerializer, $viewer),
                                    'to'      => call_user_func($userSerializer, $viewer),
                                ])
            ->build($title);
    }



    /**
     * Sanitize a string for use in filename
     *
     * TODO replace with Scrub
     *
     * @param string $string
     * @return string
     */
    private function sanitizeForFilename(string $string): string
    {
        return preg_replace("/[^A-Za-z0-9\-. ]/", '_', $string);
    }
}
