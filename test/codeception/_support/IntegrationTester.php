<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = null)
 *
 * @SuppressWarnings(PHPMD)
 */
class IntegrationTester extends \Codeception\Actor
{
    const NO_REDIRECT    = '-';
    const VAGRANT_DOMAIN = 'vagrant.dev.schoolbox.cloud';

    use _generated\IntegrationTesterActions;

    /**
     * prepareGuzzleUrl prepares the Guzzle response history into an array we can use to check the history of redirects
     *
     * @param \GuzzleHttp\Psr7\Response $response
     *
     * @return array #array[[$URL => $STATUS_CODE]]
     */
    public function prepareGuzzleRedirectHistoryToArray(Response $response): array
    {
        $returnURLs     = $response->getHeader('X-Guzzle-Redirect-History');
        $returnCodes    = $response->getHeader('X-Guzzle-Redirect-Status-History');
        $lastReturnCode = '' . $response->getStatusCode();

        if (isset($returnCodes) && $returnCodes !== []) {
            $counter            = 0;
            $array_of_redirects = [];
            while ($counter < count($returnCodes)) {
                $array_of_redirects[] = [$returnURLs[$counter] => $returnCodes[$counter]];
                $counter++;
            }
            $array_of_redirects[] = [self::NO_REDIRECT => $lastReturnCode];
            return $array_of_redirects;
        }

        return [['-' => $lastReturnCode]];
    }

    /**
     * checkCurlReturn checks the return values against the actual values that have been returned during the test.
     *
     * @param array                     $expectedReturn #array[[$URL => $STATUS_CODE]]
     * @param \GuzzleHttp\Psr7\Response $response
     */
    public function checkCurlReturn(array $expectedReturn, \GuzzleHttp\Psr7\Response $response): void
    {
        $redirectHistoryArray = $this->prepareGuzzleRedirectHistoryToArray($response);

        if (count($expectedReturn) == count($redirectHistoryArray)) {
            $numberOfReturnUrls = 0;
            while ($numberOfReturnUrls < count($redirectHistoryArray)) {
                if (!array_key_exists('-', $expectedReturn[$numberOfReturnUrls])) {
                    $this->assertEquals($expectedReturn[$numberOfReturnUrls], $redirectHistoryArray[$numberOfReturnUrls], 'Expect a redirect');
                } else {
                    $this->assertEquals($expectedReturn[$numberOfReturnUrls], $redirectHistoryArray[$numberOfReturnUrls], 'Expect last return code');
                }

                $numberOfReturnUrls++;
            }
        } else {

            $this->assertEquals(count($expectedReturn), count($redirectHistoryArray), 'Action path of request has less steps then the expected amount of steps. ' . print_r($expectedReturn) . ' compared to ' . print_r($redirectHistoryArray));
        }
    }

    /** @var CookieJar $client_cookie_jar */
    public $client_cookie_jar;

    public $guzzle_headers = [];

    public $redirect_settings;


    public function initGuzzleClient()
    {
        if (!isset($this->redirect_settings)) {
            $this->guzzle_headers    = [
                'Host'         => self::VAGRANT_DOMAIN,
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept'       => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ];
            $this->redirect_settings = [
                'max'             => 5,
                'referer'         => true,
                'protocols'       => ['http', 'https'],
                'track_redirects' => true,
            ];
            $this->client_cookie_jar = new CookieJar();
        }
    }

    /**
     * loginUser fire off Curl to login user.
     *
     * @param \Payload\Base $requestDetails request details for the curl request.
     * @param bool          $redirect       if the request should follow the redirect.
     *
     * @return Response
     */
    public function sendCurlRequest(\Payload\Base $requestDetails, bool $redirect, string $step_message = ''): Response
    {
        $this->initGuzzleClient();

        $requestDetailsArray = $requestDetails->payloadToArray();

        $method = $requestDetailsArray['method'];
        $page   = $requestDetailsArray['page'];
        $params = $requestDetailsArray['payload'];

        if ($redirect) {
            $params['allow_redirects'] = $this->redirect_settings;
        } else {
            $params['allow_redirects'] = false;
        }

        $params['cookies'] = $this->client_cookie_jar;

        if ($step_message != '') {
            $this->amGoingTo($step_message);
        }

        return $this->sendRequestCurl($method, $page, $params);
    }


    public function loginAdmin()
    {
        $loginPayload = new Payload\Login();
        $loginPayload->setUsername('alaress');
        $loginPayload->setPassword('alaress');

        $this->sendCurlRequest($loginPayload, false);
    }

    public function setDefaultRubric(Payload\Assessment $assessmentPayload): Payload\Assessment
    {

        $folder_id                  = $assessmentPayload->getFormParam('folderId');
        $capture_criterion_group_id = '/rb\[0\]\[criterion\]\[(\d+)]\[groupId\]" value="(\d+)/';
        #So that we can loop through getting the ID's for components create a skeleton to allow us to get it done. Need to update all the - values with real values.
        $rubySkeleton[0] = [
            'group'     => [
                ['id' => -1, 'name' => 'I'],
                ['id' => -2, 'name' => 'II'],
                ['id' => -3, 'name' => 'III'],
            ],
            'criterion' => [
                '-1' => [
                    'id'      => -1,
                    'groupId' => -1,
                ],
                '-2' => [
                    'id'      => -2,
                    'groupId' => -1,
                ],
                '-3' => [
                    'id'      => -3,
                    'groupId' => -1,
                ],
            ],
        ];

        $copySkeletonForIteration = $rubySkeleton;
        $counter                  = 0;
        foreach ($copySkeletonForIteration[0]['group'] as $group) {
            $capabilityName       = 'I' . $counter;
            $rubCapabilityPayload = new Payload\RubricCapability();
            $rubCapabilityPayload->setFolderId($folder_id);
            $rubCapabilityPayload->setName($capabilityName);
            $rubCapabilityPayload->setGroupIndex($counter);

            $this->sendCurlRequest($rubCapabilityPayload, false, 'send request to create capability, ' . $capabilityName);

            $counterCriterion = 0;

            ##This is a pain but I need to walk through so that I hit all the end point posts for each criterion and group id for rubrics
            foreach ($copySkeletonForIteration[0]['criterion'] as $criterion) {
                $indicatorName       = 'a' . $counterCriterion;
                $rubIndicatorPayload = new Payload\RubricIndicator();
                $rubIndicatorPayload->setFolderId($folder_id);
                $rubIndicatorPayload->setName($indicatorName);
                $rubIndicatorPayload->setGroupName($capabilityName);
                $response                   = $this->sendCurlRequest($rubIndicatorPayload, true, 'send request to create a indicator, ' . $indicatorName . ' in cabability, ' . $capabilityName);
                $criterion_group_id_matches = [];
                preg_match($capture_criterion_group_id, $response->getBody(), $criterion_group_id_matches);

                $capability_id = $criterion_group_id_matches[2];
                $indicator_id  = $criterion_group_id_matches[1];
                $assessmentPayload->addRubricCapabitity($capability_id, $rubySkeleton[0]['group'][$counter]['name']);
                $assessmentPayload->setRubricIndicator($indicator_id, $capability_id, '' . $counterCriterion, true);

                if ($counterCriterion === 2) {
                    $assessmentPayload->setRubricDescription('', $indicator_id, 0, 'A' . $counterCriterion, '');
                    $assessmentPayload->setRubricDescription('', $indicator_id, 1, 'B' . $counterCriterion, '');
                    $assessmentPayload->setRubricDescription('', $indicator_id, 2, 'C' . $counterCriterion, '');
                } else {
                    $assessmentPayload->setRubricDescription('', $indicator_id, 0, 'A' . $counterCriterion, 1);
                    $assessmentPayload->setRubricDescription('', $indicator_id, 1, 'B' . $counterCriterion, 2);
                    $assessmentPayload->setRubricDescription('', $indicator_id, 2, 'C' . $counterCriterion, 3);
                }

                $counterCriterion++;
            }

            $counter++;
        }

        $assessmentPayload->addRubricLevel('', 'A');
        $assessmentPayload->addRubricLevel('', 'B');
        $assessmentPayload->addRubricLevel('', 'C');

        return $assessmentPayload;
    }

    public function setDefaultRubricMarks(Payload\AssessmentMark $assessmentMark)
    {
        $assessmentID = $assessmentMark->getAssessmentId();
        /** @var Schoolbox\Assessment\Rubric\Grid\Grid $markGrid */
        $markGrid = $this->getAssessmentRubricGrid($assessmentID); ## Has been deprecated needs a recode.

        foreach ($markGrid->getCapabilityUsages() as $group_capability) {
            foreach ($group_capability->getIndicatorUsages() as $capability_indicator) {
                $descriptors = $capability_indicator->getDescriptors();
                $levelToPick = random_int(0, 2);
                $assessmentMark->addMarkRubricDescriptorSelect($descriptors[$levelToPick]->getId());
            }
        }
    }

    public function courseImportAddUnits(\Payload\CourseImport $courseImportPayload, Schoolbox\GenericCollection $allTheUnits)
    {
        foreach ($allTheUnits as $unit) {
            $courseImportPayload->addUnitImport($unit->getId());
        }

        return $courseImportPayload;
    }

    public function courseImportAddAssessments(\Payload\CourseImport $courseImportPayload, Schoolbox\GenericCollection $allTheUnits)
    {
        foreach ($allTheUnits as $unit) {
            $courseImportPayload->addUnitSpec($unit->getId());
            $assessments = $this->getAssessmentIdByCourseUnitSpecId($unit->getId());
            foreach ($assessments as $assessment) {
                $courseImportPayload->addAssessmentImport($unit->getId(), $assessment['assessment_id']);
            }
        }

        return $courseImportPayload;
    }

}
