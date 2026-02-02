<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\DialogAgent;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\Misconfigured;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Editor\OpenAI\Log\Mapper as AILogMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DialogAgent;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Lookup\NextRoutes;
use Schoolbox\Learning\Assessment\DueWork\Authorizer\Rule\CanSelfAssess;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Authorizer\Rule\AssessmentNotDeleted;
use Schoolbox\Learning\Authorizer\Rule\CanPreview;
use Schoolbox\Learning\Authorizer\Rule\CanRespondToAssessment;
use Schoolbox\Learning\Authorizer\Rule\CanSeeParticipation;
use Schoolbox\Learning\Authorizer\Rule\CanViewAssessable;
use Schoolbox\Learning\Authorizer\Rule\HasParticipant;
use Schoolbox\Learning\Authorizer\Rule\IsAssessmentOpen;
use Schoolbox\Learning\Authorizer\Rule\IsImplementation;
use Schoolbox\Learning\Authorizer\Rule\IsInstructorOrHasHomepageAccess;
use Schoolbox\Learning\Authorizer\Rule\IsInteractionUnLocked;
use Schoolbox\Learning\Authorizer\Rule\IsModifyUnLocked;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningDialogAgent';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Preloaders
         */
        $preloadAssessment = function ($params) {
            return $this->preloadAssessment($params);
        };

        $preloadParticipant = function ($params) {
            return $this->preloadAssessmentAndStudent($params);
        };

        /* Preload a participant+optional response via (assessment id, student id, response id) tuple,
         * returning an (assessment, student, response) tuple.
         *
         * This seems pretty long-winded for some reason: there might have been an easier way to ensure ownership
         */
        $preloadParticipantAndOptionalResponse = function ($params) use ($preloadParticipant) {
            $tuple = $preloadParticipant($params);

            /** @var DialogAgent\Model $assessment */
            $assessment = $tuple['assessment'];

            /** @var User $student */
            $student = $tuple['student'];

            $response = null;
            if (isset($params['responseId'])) {
                $participant = $assessment->getParticipantIfExtant($student);

                /* This person has been excluded from the assessment or is no longer participating */
                if (null === $participant) {
                    throw new NotFound();
                }

                /* Explore $participant's responses to find an identifier that exists for them.
                 * If one doesn't exist, they're requesting someone else's response id
                 */
                /** @var \Schoolbox\Learning\Assessment\DialogAgent\Response\Model|null $response */
                $response = $participant->getAllResponses()->getBy('id', $params['responseId']);

                /* responseId doesn't belong to the participant (or maybe wasn't hydrated properly) */
                if (null === $response) {
                    throw new NotFound();
                }
            }

            /* Add this response to the tuple we previously obtained and return that */
            $tuple['response'] = $response;

            return $tuple;
        };

        /* Preload a participant+response
         * Same as $preloadParticipantAndOptionalResponse, but ensures response exists
         */
        $preloadParticipantAndResponse = function ($params) use ($preloadParticipantAndOptionalResponse) {
            $tuple = $preloadParticipantAndOptionalResponse($params);

            /* no response found */
            if (null === $tuple['response']) {
                throw new NotFound();
            }

            return $tuple;
        };

        /* Shortcuts to Authorization Rule constructs
         */
        $isSuper = $this->getAuthRule('isSuperuser');
        $canViewResponse = $this->buildAuthRuleCanViewResponse();
        $canSubmit = $this->buildAuthRuleCanSubmit();
        $canWrite = $this->buildAuthRuleHasInstructorOrHomepageAccess();
        $isFeatureEnabled = $this->buildIsFeatureEnabledAuthRule();

        $isAuthoritativeResponse = new Rule\Callback(function (array $params): void {
            /** @var \Schoolbox\Learning\Assessment\DialogAgent\Response\Model|null $response */
            $response = $params['response'] ?? null;
            if (null === $response || !$response->getAuthoritative()) {
                throw new Unauthorized('This action can only be performed on an authoritative response');
            }
        });

        $isNotAuthoritativeResponse = new Rule\Callback(function (array $params): void {
            /** @var \Schoolbox\Learning\Assessment\DialogAgent\Response\Model|null $response */
            $response = $params['response'] ?? null;
            if (null === $response || $response->getAuthoritative()) {
                throw new Unauthorized('This action can only be performed on a non-authoritative response');
            }
        });

        $isFeedbackRequired = new Rule\Callback(function (array $params): void {
            /** @var DialogAgent\Model $assessment */
            $assessment = $params['assessment'];

            $participant = $assessment->getParticipant($params['student']);

            if (!$assessment->isFeedbackRequired($participant)) {
                throw new Unauthorized('Feedback is not required for this participant');
            }
        });

        /**
         * For a learner to be able to interact with a response, they must meet the canonical "can submit" criteria,
         * and the response must be authoritative.
         */
        $canInteractWithResponse = new Rule\All(
            $canSubmit,
            $isAuthoritativeResponse,
        );

        $canModify = new Rule\Any(
            $isSuper,
            new Rule\All(
                $canWrite,
                new AssessmentNotDeleted(),
                new IsModifyUnLocked(new LockedChecker($this->getTermMapper()))
            ),
        );

        /* Route Definitions
         */
        $assRoute  = '/{assessmentId:\d+}';
        $studentParam  = '{studentId:\d+}';
        $responseParam = '{responseId:\d+}';
        $optionalResponseParam = '{/responseId:\d+}';

        /* Create a dialog agent's configuration
         */
        $this->register('GET', "{$assRoute}/configure", 'getUpdateForm', new Rule\All(
            $isFeatureEnabled,
            $canModify,
        ), $preloadAssessment);
        $this->register('POST', "{$assRoute}/configure", 'postUpdate', new Rule\All(
            $isFeatureEnabled,
            $canModify,
        ), $preloadAssessment);

        /* Instructor requests AI to generate initial field configuration
         */
        $this->register('GET', "{$assRoute}/configure/generateFields", 'getGenerateConfigurationFields', new Rule\All(
            $isFeatureEnabled,
            $canModify,
        ), $preloadAssessment);

        /* Instructor sends a message to the chat agent sandbox
         */
        $this->register('POST', "{$assRoute}/configure/message", 'postSandboxMessage', new Rule\All(
            $isFeatureEnabled,
            $canModify,
        ), $preloadAssessment);

        /* Get the status of a particular response
         */
        $this->register('GET', "{$assRoute}/{$studentParam}/status/{$responseParam}", 'getStatus', new Rule\All(
            $canViewResponse,
            $this->buildIsConfiguredAuthRule()
        ), $preloadParticipantAndResponse);

        /* View a response (readonly mode)
         * or fall back to the authoritative response if responseId is not specified
         */
        $this->register('GET', "{$assRoute}/{$studentParam}/view{$optionalResponseParam}", 'getParticularResponse', new Rule\All(
            $canViewResponse,
            $this->buildRuleCanAction(NextRoutes::ACTION_VIEW),
            $this->buildIsConfiguredAuthRule()
        ), $preloadParticipantAndOptionalResponse);

        /* Redirecting route to find the first participant in the assessment
         */
        $this->register('GET', "{$assRoute}/responses/view", 'getFirstParticipantResponse', new Rule\All(
            $canWrite,
            $this->buildIsConfiguredAuthRule(),
            new IsImplementation(),
            new HasParticipant(),
        ), $preloadAssessment);

        /* Student sends a message to the chat agent for a particular response
         */
        $this->register('POST', "{$assRoute}/{$studentParam}/message/{$responseParam}", 'postMessage', new Rule\All(
            $isFeatureEnabled,
            $canInteractWithResponse,
            $this->buildRuleCanAction(NextRoutes::ACTION_MESSAGE),
            $this->buildIsConfiguredAuthRule()
        ), $preloadParticipantAndResponse);

        /* Student requests assistance for a particular response
         */
        $this->register('POST', "{$assRoute}/{$studentParam}/assistance/{$responseParam}", 'postAssistanceRequest', new Rule\All(
            $isFeatureEnabled,
            $canInteractWithResponse,
            $this->buildRuleCanAction(NextRoutes::ACTION_ASSISTANCE_REQUEST),
            $this->buildIsConfiguredAuthRule()
        ), $preloadParticipantAndResponse);

        /* A student who asked for assistance got their situation resolved and can have the assistance request cleared.
         * This action could be from an instructor or from the student dismissing it themself
         */
        $this->register('POST', "{$assRoute}/{$studentParam}/resolve/{$responseParam}", 'postAssistanceResolve', new Rule\All(
            $isFeatureEnabled,
            new Rule\Any(
                $canWrite,
                $canInteractWithResponse,
            ),
            $this->buildRuleCanAction(NextRoutes::ACTION_ASSISTANCE_RESOLVE),
            $this->buildIsConfiguredAuthRule()
        ), $preloadParticipantAndResponse);

        /* Either a student hits the submit button to finalise their dialog agent discussion,
         * or an instructor forcibly finalises a response on behalf of the student
         */
        $this->register('POST', "{$assRoute}/{$studentParam}/submit/{$responseParam}", 'postSubmit', new Rule\All(
            $isFeatureEnabled,
            new Rule\Any(
                $canWrite,
                $canSubmit,
            ),
            $isAuthoritativeResponse,
            $this->buildRuleCanAction(NextRoutes::ACTION_SUBMIT),
            $this->buildIsConfiguredAuthRule()
        ), $preloadParticipantAndResponse);

        /* Reopens a dialog agent chat, which basically unsubmits the authoritative response
         */
        $this->register('POST', "{$assRoute}/{$studentParam}/reopen/{$responseParam}", 'postReopen', new Rule\All(
            $isFeatureEnabled,
            $canWrite,
            $this->buildRuleCanAction(NextRoutes::ACTION_REOPEN),
            $this->buildIsConfiguredAuthRule()
        ), $preloadParticipantAndResponse);

        /* Resets a dialog agent for a particular person. This creates a new authoritative response instance,
         * setting all other responses to non-authoritative, basically letting students start afresh.
         */
        $this->register('POST', "{$assRoute}/{$studentParam}/reset/{$responseParam}", 'postReset', new Rule\All(
            $isFeatureEnabled,
            $canWrite,
            $this->buildRuleCanAction(NextRoutes::ACTION_RESET),
            $this->buildIsConfiguredAuthRule()
        ), $preloadParticipantAndResponse);

        /* Change a particular response to be the authoritative one for the participant
         */
        $this->register('POST', "{$assRoute}/{$studentParam}/setAuthoritative/{$responseParam}", 'postSetAuthoritative', new Rule\All(
            $isFeatureEnabled,
            $canWrite,
            $isNotAuthoritativeResponse,
            $this->buildIsConfiguredAuthRule()
        ), $preloadParticipantAndResponse);

        /* Exports everyone's Response Status and Feedback Contents (if any exist) into a csv format.
         */
        $this->register('GET', "{$assRoute}/export", 'getExport', $canWrite, $preloadAssessment);

        /* Generate feedback for a particular dialog agent response
         */
        $this->register('POST', "$assRoute/{$studentParam}/feedback/{$responseParam}", 'postGenerateFeedback', new Rule\All(
            $isFeatureEnabled,
            $canViewResponse,
            $isAuthoritativeResponse,
            $isFeedbackRequired,
            $this->buildIsConfiguredAuthRule()
        ), $preloadParticipantAndResponse);
    }


    /**
     * Ensure the Dialog Agent feature is enabled
     *
     * @return Rule\IAuthorizationRule
     */
    private function buildIsFeatureEnabledAuthRule(): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(
            fn() => new Rule\Callback(function (array $params): void {
                if (new ValidDialogAgentEnabled($this->getConfig())->isSatisfied()) {
                    return;
                }

                throw new Unauthorized('Feature is not enabled');
            })
        );
    }



    /**
     * Ensure the Dialog Agent is fully configured first
     *
     * @return Rule\IAuthorizationRule
     */
    private function buildIsConfiguredAuthRule(): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(
            fn() => new Rule\Callback(function (array $params): void {
                if ((new DialogAgent\ValidConfiguration($this->injector->get('databaseInternal')))->isSatisfiedBy($params['assessment'])) {
                    return;
                }

                throw new Misconfigured();
            })
        );
    }


    /**
     * Convert route params into a participant object
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Application\Exception\Exception If assessment is not found
     * @throws \DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws \LogicException If cyclic graph detected
     * @throws \Schoolbox\Mapper\DefinitionException If a specified join mapper is not found
     * @throws NotFound
     */
    protected function preloadAssessmentAndStudent(array $params): array
    {
        try {
            // obtain the assessment from our other preload
            $student     = $this->getUserMapper()->findById($params['studentId']);
            $assessment  = $this->preloadAssessment($params, $student)['assessment'];
        } catch (\UnexpectedValueException $e) {
            // Student isn't a participant
            throw new NotFound();
        } catch (\OutOfBoundsException $e) {
            // given user/assessment combination is invalid: not a participant
            throw new NotFound();
        } catch (MapperException $e) {
            // either assessment or user was not found
            throw new NotFound();
        }

        return [
            'assessment'  => $assessment,
            'student'     => $student, //IsParentOfStudent accesses this
        ];
    }


    // will throw invalid arg if route params incorrect
    /**
     * To view a summary page requires:
     * - assessment not soft-deleted
     * - current user can view the assessment
     * - either:
     *   - the current user is an instructor, and viewing their own summary, or:
     *   - the current user may see the participation of the student who is the
     *     subject of the summary
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException If required route param not found
     */
    private function buildAuthRuleCanViewResponse(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new AssessmentNotDeleted(),
            /* Only students and staff can see this, specifically excluding parents and guests.
             * This overlaps CanViewAssessable() to some extent
             */
            new Rule\Any(
                new Rule\HasRole(Role::STUDENT),
                new Rule\HasRole(Role::STAFF),
            ),
            /* The rules below were yoinked from Learning/Assessment/Module.
             */
            new CanViewAssessable(new Visibility($this->getAccess())),
            new Rule\Any(
                // Instructor/staff viewing their own summary page
                new Rule\All(
                    $this->buildAuthRuleHasInstructorOrHomepageAccess(),
                    new Rule\IsUser('studentId')
                ),
                // Normal participants (whether explicit or dynamic):
                // Check after instructor check, so that the summary can show a
                // polite "User {studentID} is excluded from this assessment"
                // message.
                new CanSeeParticipation($this->getAccess(), $this->getConfig()->get('restrict_student_submissions'))
            ),
        );
    }


    /**
     * Rules in order of preference:
     * - SU can submit
     * - staff/students cannot if due in archived term (ie 'interaction locked')
     * - staff must have WRITE+ perm to folder
     * - students cannot if not open
     * - Students cannot submit if reached response limit
     * - students cannot if not participant, or a different student to that indicated in URL
     * - Students cannot submit if assessment does not accept online submissions AND self-reflect is off
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException If required object not found in route params
     */
    private function buildAuthRuleCanSubmit()
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\All(
                    new Rule\Any(
                    // (Superuser + impl) or (instructor + !interaction-locked)
                        new Rule\All(
                            new Rule\IsSuperuser(),
                            new IsImplementation()
                        ),
                        new Rule\All(
                            $this->buildAuthRuleHasInstructorOrHomepageAccess(),
                            new IsInteractionUnLocked(new LockedChecker($this->getTermMapper()))
                        )
                    ),
                    new Rule\Any(
                        new CanRespondToAssessment($this->getAccess()), // SOBO
                        new CanPreview($this->getAccess())              // Preview
                    )
                ),
                new Rule\All(
                    new CanViewAssessable(new Visibility($this->getAccess())),
                    new Rule\IsUser('studentId'),
                    new IsInteractionUnLocked(new LockedChecker($this->getTermMapper())),
                    new IsAssessmentOpen(),
                    new Rule\Any(
                        new CanRespondToAssessment($this->getAccess()),
                        new CanSelfAssess()
                    )
                )
            )
        );
    }


    /**
     * TODO Technical Uplift Candidate
     *  We just duped this from Assessment\Module, due to this being a two-part configuration
     *  Also a bunch of related bits and bobs came with it, including all the preloaders and perms
     *
     * Prevent non-instructors from marking or modifying implementation
     * assessments, and users without homepage write access from modifying spec
     * assessments.
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException
     */
    private function buildAuthRuleHasInstructorOrHomepageAccess(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new IsInstructorOrHasHomepageAccess(
                    $this->getAccess(),
                    function (array $params) {
                        if (isset($params['assessment'])) {
                            $ctx = $params['assessment']->getContext();
                        } elseif (isset($params['participant'])) {
                            $ctx = $params['participant']->getAssessable()->getContext();
                        } elseif (isset($params['context'])) {
                            $ctx = $params['context'];
                        } else {
                            throw new NotFound('No assessment or context preloaded');
                        }
                        return $ctx;
                    },
                ),
            )
        );
    }


    /**
     * Decide if the desired action $action can be conducted for $params['response'] instance.
     *
     * This allows you to ask the $router if you can get to a place without having to directly interact with the
     * Status class and having to interpret its output struct manually
     *
     * @param string $action
     * @return Rule\IAuthorizationRule
     */
    private function buildRuleCanAction(string $action): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(
            fn() => new Rule\Callback(function (array $params) use ($action): void {
                $nextRoutes = (new DialogAgent\Response\Lookup\NextRoutes())->calculate($params['response'] ?? null);

                if (!$nextRoutes[$action]) {
                    throw new Forbidden(\sprintf(
                        'Action "%s" is not allowed',
                        $action,
                    ));
                }
            })
        );
    }


    /**
     * Convert route params into an assessment object
     *
     * @param array $params
     * @param User|null $student If provided, only load this student's participation
     * @return array
     * @throws ContainerExceptionInterface
     * @throws Deleted
     * @throws NotFoundExceptionInterface
     */
    protected function preloadAssessment(array $params, ?User $student = null): array
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId'], $student);
        } catch (MapperException\Deleted $e) {
            throw new MapperException\Deleted('Sorry, the activity could not be updated. Please ensure the activity still exists and contact your support if the problem persists.');
        } catch (MapperException $e) {
            // Possibly thrown by base findByOne() mapper call, but masked by overriding @method annotation
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        return [
            'assessment' => $assessment
        ];
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        /** @var AssessmentMapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $this->injector->get('assessmentDataSource');

        /** @var Participant\DataSource */
        $participantDataSource = $this->injector->get('participantDataSource');

        /** @var \Schoolbox\Learning\Assessment\DialogAgent\Response\MapperProxy $responseMapperProxy */
        $responseMapperProxy = $this->injector->get('dialogAgentResponseDataSource');

        $baseUrl = new \Schoolbox\Url\Factory($this->getConfig())->buildFullyQualified();
        $access = $this->getMapperFactory()->get(Access::class);

        return new Controller(
            $access,
            new \Schoolbox\Learning\Assessment\Breadcrumb\Factory(
                $this->getRouter()->getGenerator(),
                $this->getAuthorizer(),
                $access,
            ),
            $assessmentMapperProxy,
            $baseUrl,
            $this->injector->get('httpFactory'),
            $this->injector->get('taskd.httpRequestMessageFactory'),
            $this->injector->get('taskd.messageProducer'),
            $participantDataSource,
            $this->injector->get('ai.clientFactory'),
            $this->getMapperFactory()->get(AILogMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Admin\DialogAgent\Scenario\Criterion\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Criterion\Mapper::class),
            $responseMapperProxy,
            $this->getMapperFactory()->get(\Schoolbox\Admin\DialogAgent\Scenario\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Admin\DialogAgent\Scenario\Criterion\Mapper::class),
            $this->injector->get('dialogAgentFeedbackDataSource'),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\DifferentiationProfile\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Admin\DifferentiationProfiles\Mapper::class),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
    }


    /**
     * @return AssessmentMapperProxy
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getAssessmentMapperProxy(): AssessmentMapperProxy
    {
        return $this->injector->get('assessmentDataSource');
    }


    /**
     * @return UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper(): UserMapper
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }


    /**
     * @return ErrorMapper
     * @throws InvalidArgumentException
     */
    protected function getErrorMapper(): ErrorMapper
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }


    /**
     * @return Access
     * @throws InvalidArgumentException
     */
    protected function getAccess(): Access
    {
        return $this->getMapperFactory()->get(Access::class);
    }


    /**
     * @return \Schoolbox\Assessment\Term\Mapper
     * @throws \InvalidArgumentException If unable to find mapper class
     */
    private function getTermMapper(): \Schoolbox\Assessment\Term\Mapper
    {
        return $this->getMapperFactory()->get(\Schoolbox\Assessment\Term\Mapper::class);
    }
}
