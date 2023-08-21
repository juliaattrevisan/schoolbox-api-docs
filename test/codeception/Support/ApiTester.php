<?php

namespace Tests\Support;

use Tests\Support\_generated\ApiTesterActions;

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
 * @method void pause()
 *
 * @SuppressWarnings(PHPMD)
 */
class ApiTester extends \Codeception\Actor
{
    use ApiTesterActions;

    /**
     * Get token
     * @param int $userId
     * @return mixed
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getToken(int $userId)
    {
        $token = $this->getAdminToken();
        $this->amBearerAuthenticated($token);
        $this->haveHttpHeader('Accept', 'application/json');
        $this->sendPost("/user/token/{$userId}");
        return $this->grabDataFromResponseByJsonPath('$.token')[0];
    }

    /**
     * Log in as the user given
     * @param \Schoolbox\User $user
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function switchUser(\Schoolbox\User $user)
    {
        $this->comment("Log in as {$user->getUsername()} ({$user->getId()})");
        $token = $this->getToken($user->getId());
        $this->amBearerAuthenticated($token);
        $this->haveHttpHeader('Accept', 'application/json');
        $this->haveHttpHeader('X-Requested-With', 'xmlhttprequest');
    }


    /**
     * @param array $response
     * @param array $schema
     * @return void
     */
    public function checkSchema(array $response, array $schema): void
    {
        $notDocumentedProperties = array_diff(array_keys($response), array_keys($schema['properties']));
        $this->assertEmpty($notDocumentedProperties, 'Checked properties missing in document.');

        // iterate through documented properties
        foreach ($schema['properties'] as $key => $value) {
            if (!isset($value['type'])) {
                $this->comment("$key doesn't have type : " . json_encode($value));
                continue;
            }
            if ($value['type'] === 'object') {
                if (!isset($value['properties'])) {
                    $this->comment("Object $key doesn't have properties");
                    continue;
                }
                foreach ($value['properties'] as $subKey => $subValue) {
                    if (!isset($subValue['type'])) {
                        $this->comment("$key doesn't have type : " . json_encode($value));
                        continue;
                    }
                    $responseVal = $this->grabDataFromResponseByJsonPath("$.data[0].$key.$subKey");
                    if ($responseVal[0] === null) {
                        $this->assertTrue(array_key_exists('nullable', $subValue));
                    } else {
                        $this->seeResponseMatchesJsonType([$subKey => $subValue['type']], "$.data[0].$key");
                    }
                }
            } else {
                $responseVal = $this->grabDataFromResponseByJsonPath("$.data[0].$key");
                if ($responseVal[0] === null) {
                    $this->assertTrue(array_key_exists('nullable', $value));
                } else {
                    $this->seeResponseMatchesJsonType([$key => $value['type']], "$.data[0]");
                }
            }

            $this->assertArrayHasKey($key, $response, "Checked whether property $key in document exists in response.");
        }
    }
}
