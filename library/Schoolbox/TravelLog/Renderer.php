<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\TravelLog;

use Alaress\Redis;
use Alaress_Date_Format;

Class Renderer
{

    // Student's Travel Log should be requested and displayed only for verso school;
    private const VERSO_SCHOOL_CODE = 'verso';
    private const OPEN_TABLE        = '<table>
                                            <thead>
                                                <tr>
                                                    <th>TYPE</th>
                                                    <th>ACTION & LOCATION</th>
                                                    <th>TIME</th>
                                                </tr>
                                             </thead>
                                             <tbody>';

    private const CLOSE_TABLE = '            </tbody>
                                       </table>';


    /**
     * @var string
     */
    private $schoolCode;

    /**
     * @var ?string
     */
    private $clientId;

    /**
     * @var ?string
     */
    private $clientSecret;

    /**
     * @var string
     */
    private $cachingTimeout;

    /**
     * @var \Component_Dashboard_ChildSubjectController
     */
    private $childSubjectController;


    /**
     * Renderer constructor.
     * @param string                                      $schoolCode
     * @param string|null                                 $clientId
     * @param string|null                                 $clientSecret
     * @param string                                      $cachingTimeout
     * @param \Component_Dashboard_ChildSubjectController $childSubjectController
     */
    public function __construct(string $schoolCode, ?string $clientId, ?string $clientSecret, string $cachingTimeout, \Component_Dashboard_ChildSubjectController $childSubjectController)
    {
        $this->schoolCode             = $schoolCode;
        $this->clientId               = (string)$clientId;
        $this->clientSecret           = (string)$clientSecret;
        $this->cachingTimeout         = $cachingTimeout;
        $this->childSubjectController = $childSubjectController;
    }


    /**
     * @param string $childId
     */
    public function render(string $childId): void
    {
        $this->childSubjectController->setBlock('student_block', 'travel_log', 'travel_log_block');
        $this->childSubjectController->setBlock('travel_log', 'log', 'log_block');
        $this->childSubjectController->parse('travel_log_block', 'travel_log');


        /*
         * If school code is not verso or the gateway credentials were not provided,
         * then the student travel logs table will not be rendered.
         * If anything goes wrong with the api, no error message will be displayed to users,
         * as that will not be helpful/meaningful for them (parents, students or staffs).
         *
         * Possible error messages cannot be displayed for admins as they don't have such
         * screen unless they are admin and parent at the same time.
        */
        if ($this->isVerso() && $this->isCredentialValid()) {


            // Get the data from Verso API.
            $travelLogs = (new TravelLog($this->clientId, $this->clientSecret, Redis::getInstance()))->getForStudentCached($childId, $this->cachingTimeout);

            if (isset($travelLogs['data']) && !empty($travelLogs['data'])) {

                $this->childSubjectController->setVar('OPEN_TABLE', self::OPEN_TABLE);
                $this->childSubjectController->setVar('log_block', '');

                foreach ($travelLogs['data'] as $log) {
                    $this->childSubjectController->setVar([
                        'TYPE'                => ucfirst($log['event_type']),
                        'ACTION_AND_LOCATION' => $log['location_tag'],
                        'DIRECTION'           => $log['direction'] === 'in' ? 'enter' : 'exit',
                        'DIRECTION_CLASS'     => $log['direction'] === 'in' ? 'reviewed' : 'submitted-late',
                        'TIME'                => Alaress_Date_Format::newNaturalDate($log['timestamp'])->getTimeOnly(),
                    ]);

                    $this->childSubjectController->parse('log_block', 'log', true);
                }

                $this->childSubjectController->setVar('CLOSE_TABLE', self::CLOSE_TABLE);


            } else {
                $this->childSubjectController->setVar("travel_log_block", '');
            }

        } else {

            $this->childSubjectController->setVar('travel_log_block', '');

        }
    }

    /**
     *
     * @return bool
     */
    private function isVerso(): bool
    {
        return $this->schoolCode === self::VERSO_SCHOOL_CODE;
    }

    /**
     *
     * Check whether credentials were provided and are not null
     * @return bool
     */
    private function isCredentialValid(): bool
    {
        return ($this->clientId !== '' && $this->clientSecret !== '');
    }

}
