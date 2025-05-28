<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Download;

use Alaress_Date_Format;
use Aura\Router\Generator;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as DueWorkResponseFile;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\TaskDaemon\Request\DownloadPages\MessageFactory as BaseMessageFactory;
use Schoolbox\TaskDaemon\Request\Message;
use Schoolbox\Url\Uri;
use Schoolbox\User;
use Schoolbox\User\Translator\ModelToArray as UserTranslator;

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
     * Standard date format for files generated within the ZIP file.
     */
    private const STANDARD_DATE_FORMAT = 'Ymd-His';

    /**
     * Constructor
     *
     * @param BaseMessageFactory $baseMessageFactory Required to actually build messages.
     * @param Uri                $urlBase            Required to build URIs which point back to this instance.
     * @param RosettaStone       $rosettaStone       Required to translate the term "due work" for this instance.
     * @param Generator          $routeGenerator     Required to build routes to download various due work parts.
     * @param UserTranslator     $userTranslator     Required to translate the viewer to an array representation for
     *                                               inclusion within the sent message.
     * @param bool               $turnitinEnabled    Is Turnitin enabled for this instance? If so, download TII reports.
     */
    public function __construct(
        private BaseMessageFactory $baseMessageFactory,
        private Uri $urlBase,
        private RosettaStone $rosettaStone,
        private Generator $routeGenerator,
        private UserTranslator $userTranslator,
        private bool $turnitinEnabled,
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
     * @param DueWork  $dueWork The due work to build a message for.
     * @param Array    $studentUserIds Array of student user IDs (those who submitted the responses).
     * @param User     $viewer  The user who is requesting the due work ZIP.
     * @return Message
     */
    public function buildForAssessment(DueWork $dueWork, $studentUserIds, User $viewer): Message
    {
        // Generate list of files to fetch via taskd, and add to a ZIP
        $messageFactory = (clone $this->baseMessageFactory);

        // Iterate participants and get their responses
        foreach ($dueWork->getAllParticipants() as $participant) {
            // Short-circuit if userId not in $studentUserIds array or if there's no interactions.
            // (draft feedback does count, though)
            if (
                !in_array($participant->getUser()->getId(), $studentUserIds, true) ||
                (!$participant->hasSubmittedResponse() && $participant->getAllFeedbacks()->count() === 0)
            ) {
                continue;
            }

            // Iterate all their submitted responses
            foreach ($participant->getSubmittedResponses() as $response) {
                // If somehow the response isn't a due work response: skip
                if (!$response instanceof DueWorkResponse) {
                    continue;
                }

                // Iterate all files in that response and add them to the ZIP
                foreach ($response->getAllFiles() as $file) {
                    $messageFactory = $messageFactory->addFileContent(
                        $this->generateResponseFileName($participant, $response, $file),
                        $this->generateResponseFileUrl($file),
                    );

                    // Add turnitin report alongside student submitted files
                    if ($this->turnitinEnabled) {
                        $turnitinSubmissionId = $file->getTurnItIn()?->getTurnitinSubmissionId();
                        if (isset($turnitinSubmissionId)) {
                            $messageFactory = $messageFactory->addTurnitinContent(
                                $this->generateResponseFileTurnitinName($participant, $response, $file),
                                $turnitinSubmissionId,
                            );
                        }
                    }
                }
            }

            // Iterate all their published feedbacks
            foreach ($participant->getPublishedFeedbacks() as $feedback) {
                // If somehow the feedback isn't a due work response: skip
                if (!$feedback instanceof DueWorkFeedback) {
                    continue;
                }

                // if the feedback was not created by an instructor, don't save it to the zip
                if (true === !$feedback->isInstructorGiven()) {
                    continue;
                }

                // current feedback was created by an instructor
                // loop through the list of file
                foreach ($feedback->getAllFiles() as $feedbackFile) {
                    // attach the files to the taskd download object
                    $messageFactory = $messageFactory->addFileContent(
                        $this->generateResponseFileFeedbackName($participant, $feedback, $feedbackFile),
                        $this->generateResponseFileUrl($feedbackFile),
                    );
                }

                // If there's a response to this feedback:
                // add any Kami files to the feedback folder
                $feedbackResponse = $feedback->getResponse();
                if ($feedbackResponse) {
                    // add Kami files
                    foreach ($feedbackResponse->getAllFiles() as $file) {
                        $kamiDocumentId = $file->getKami()?->getKamiDocumentId();
                        if (isset($kamiDocumentId)) {
                            $messageFactory = $messageFactory->addKamiContent(
                                $this->generateResponseFileKamiName($participant, $feedback, $file),
                                $kamiDocumentId,
                            );
                        }
                    }
                }
            }

            // Add this participant's submission history
            $messageFactory = $messageFactory->addPdfContent(
                $this->generateParticipantSubmissionHistoryFileName($participant),
                null,
                $this->generateParticipantSubmissionHistoryUrl($participant),
            );
        }

        // Build the message to send
        $zipFilename    = $this->generateZipFilename($dueWork);
        $callbackUrl    = $this->generateCallbackUrl($viewer);
        $callbackUser   = call_user_func($this->userTranslator, $viewer);
        $callbackParams = [
            'from' => $callbackUser,
            'to'   => $callbackUser,
        ];
        return $messageFactory
            ->setUser($viewer)
            ->setCallbackUrl($callbackUrl)
            ->setCallbackParams(
                $callbackParams + [
                    'message' => sprintf(
                        'Feedback and submissions for %s "%s" are ready to download',
                        $this->rosettaStone->translate('dueWork'),
                        $zipFilename,
                    ),
                ]
            )
            ->setFailureCallbackUrl($callbackUrl)
            ->setFailureCallbackParams(
                $callbackParams + [
                    'message' => sprintf(
                        'Feedback and submissions for %s "%s" could not be downloaded (reason: :message)',
                        $this->rosettaStone->translate('dueWork'),
                        $zipFilename,
                    ),
                    'messageParams' => [
                        'message' => '{{response.message}}',
                    ],
                ]
            )
            ->setOutfile($zipFilename)
            ->build();
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

    /**
     * Generate a base folder name for this participant.
     *
     * @param Participant $participant
     * @return string
     */
    private function generateParticipantFolderName(Participant $participant): string
    {
        return sprintf(
            "%s_%s_%d",
            $this->sanitizeForFilename($participant->getUser()->getLastname()),
            $this->sanitizeForFilename($participant->getUser()->getFirstname()),
            $participant->getUser()->getId(),
        );
    }

    /**
     * Generates a base folder name for this participant and response.
     *
     * @param Participant $participant
     * @param IResponse   $response
     * @return string
     */
    private function generateResponseFolderName(Participant $participant, IResponse $response): string
    {
        return sprintf(
            "%s/%s-submission",
            $this->generateParticipantFolderName($participant),
            $response->getSubmittedAt()->getUsingFormat(self::STANDARD_DATE_FORMAT),
        );
    }

    /**
     * Generates a base folder name for this participant and response.
     *
     * @param Participant $participant
     * @param IFeedback   $feedback
     * @return string
     */
    private function generateFeedbackFolderName(Participant $participant, IFeedback $feedback): string
    {
        return sprintf(
            "%s/%s-feedback",
            $this->generateParticipantFolderName($participant),
            $feedback->getPublishedAt()->getUsingFormat(self::STANDARD_DATE_FORMAT),
        );
    }

    /**
     * Generates a file name for this participant, response and file in the
     * generated ZIP file.
     *
     * @param Participant $participant
     * @param IResponse   $response
     * @param IStorage    $responseFile
     * @return string
     */
    private function generateResponseFileName(
        Participant $participant,
        IResponse $response,
        IStorage $responseFile,
    ): string {
        return sprintf(
            "%s/%s",
            $this->generateResponseFolderName($participant, $response),
            $this->sanitizeForFilename($responseFile->getBasename()),
        );
    }

    /**
     * Generates a download URL for this file.
     *
     * @param IStorage $responseFile
     * @return string
     */
    private function generateResponseFileUrl(IStorage $responseFile): string
    {
        return (string)$this->urlBase
            ->withPath('/storage/fetch.php')
            ->appendQueryParameters(['hash' => $responseFile->getHash()]);
    }

    /**
     * Generates a file name for this participant, feedback and Kami file in the
     * generated ZIP file.
     *
     * @param Participant         $participant
     * @param IFeedback           $feedback
     * @param DueWorkResponseFile $responseFile
     * @return string
     */
    private function generateResponseFileKamiName(
        Participant $participant,
        IFeedback $feedback,
        DueWorkResponseFile $responseFile,
    ): string {
        // Does this basename have a .pdf extension already?
        // If so, keep it: otherwise append .pdf
        $basename = match (strtolower($responseFile->getExtension())) {
            'pdf'   => $responseFile->getFilename(),
            default => $responseFile->getBasename(),
        };
        return sprintf(
            "%s/annotated/%s.pdf",
            $this->generateFeedbackFolderName($participant, $feedback),
            $this->sanitizeForFilename($basename),
        );
    }

    /**
     * Generates a file name for this participant, feedback and Turnitin file in
     * the generated ZIP file.
     *
     * @param Participant         $participant
     * @param IResponse           $response
     * @param DueWorkResponseFile $responseFile
     * @return string
     */
    private function generateResponseFileTurnitinName(
        Participant $participant,
        IResponse $response,
        DueWorkResponseFile $responseFile,
    ): string {
        // Does this basename have a .pdf extension already?
        // If so, keep it: otherwise append .pdf
        $basename = match (strtolower($responseFile->getExtension())) {
            'pdf'   => $responseFile->getFilename(),
            default => $responseFile->getBasename(),
        };
        return sprintf(
            "%s/similarity-report/%s.pdf",
            $this->generateResponseFolderName($participant, $response),
            $this->sanitizeForFilename($basename),
        );
    }

    /**
     * Generates a file name for this participant's submission history in the
     * generated ZIP file.
     *
     * @param Participant $participant
     * @return string
     */
    private function generateParticipantSubmissionHistoryFileName(Participant $participant): string
    {
        return sprintf("%s/submissionHistory.pdf", $this->generateParticipantFolderName($participant));
    }

    /**
     * Generate a download URL for this participant's submission history.
     *
     * @param Participant $participant
     * @return string
     * @noinspection PhpDocMissingThrowsInspection - this route always exists
     */
    private function generateParticipantSubmissionHistoryUrl(Participant $participant): string
    {
        /** @noinspection PhpUnhandledExceptionInspection - this route always exists */
        $submissionHistoryPath = $this->routeGenerator->generate(
            'learningAssessment.getSummary',
            [
                'assessmentId' => $participant->getAssessable()->getId(),
                'studentId'    => $participant->getUser()->getId(),
            ],
        );
        return (string)$this->urlBase
            ->withPath($submissionHistoryPath);
    }

    /**
     * Generates the callback URL for the task daemon request.
     *
     * The ZIP will be attached to the notification raised by this file.
     *
     * @param User $viewer
     * @return string
     * @noinspection PhpDocMissingThrowsInspection - this route always exists
     */
    private function generateCallbackUrl(User $viewer): string
    {
        /** @noinspection PhpUnhandledExceptionInspection - this route always exists */
        $callbackPath = $this->routeGenerator->generate(
            'apiusernotify.apiUserNotify',
            ['userId' => $viewer->getId()]
        );
        return (string)$this->urlBase
            ->withPath($callbackPath);
    }

    /**
     * Generates a filename for the downloaded ZIP file.
     *
     * @param DueWork $dueWork
     * @return string
     */
    private function generateZipFilename(DueWork $dueWork): string
    {
        return sprintf(
            "%s-%s.zip",
            $this->sanitizeForFilename($dueWork->getTitle()),
            Alaress_Date_Format::now()->getUsingFormat(self::STANDARD_DATE_FORMAT),
        );
    }

    /**
     * Generates the filename for the instructor feedback response file in the
     * generated ZIP file.
     *
     * @param Participant         $participant
     * @param IFeedback           $feedback
     * @param \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile\Model $responseFile
     * @return string
     */
    private function generateResponseFileFeedbackName(
        Participant $participant,
        IFeedback $feedback,
        \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile\Model $responseFile,
    ): string {
        //these are all files, so should be fine...
        return sprintf(
            "%s/%s",
            $this->generateFeedbackFolderName($participant, $feedback),
            $this->sanitizeForFilename($responseFile->getBasename()),
        );
    }
}
