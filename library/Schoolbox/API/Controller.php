<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\API;

use Alaress\Web\Controller\Base;
use Authenticator;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends Base {

    /** @var Authenticator */
    protected $auth;

    /** @var RegistryMapper */
    protected $registryMapper;


    /**
     * @param Authenticator $auth
     * @return self
     */
    public function setAuth(Authenticator $auth)
    {
        $this->auth = $auth;
        return $this;
    }


    /**
     * @param RegistryMapper $registryMapper
     * @return self
     */
    public function setRegistryMapper($registryMapper)
    {
        $this->registryMapper = $registryMapper;
        return $this;
    }


    /**
     * @param string $provider
     */
    public function register($provider)
    {
        $data     = (array)$this->getRequest()->post;
        $response = $this->getResponse()->content;

        // check the provider is valid
        if (!in_array($provider, array(Registry::PROVIDER_APPLE, Registry::PROVIDER_GOOGLE))) {
            $this->getResponse()->status->setCode(400);
            $response->set(array(
                'format' => 'json',
                'data'   => "Invalid provider: '$provider'. Accepted providers are: ".implode(', ', array(Registry::PROVIDER_APPLE, Registry::PROVIDER_GOOGLE))
            ));
            return;
        }


        try {
            // require these to be present in POSTDATA
            $this->assertArrayKeys($data, array('token', 'username', 'password'));

        } catch (\UnderflowException $e) {
            $this->getResponse()->status->setCode(400);
            $response->set(array(
                'format' => 'json',
                'data'   => array(
                    'required' => $e->getMessage()
                )
            ));
            return;
        }

        // verify username|password combination
        try {
            $this->auth->authenticate($data['username'], $data['password'], true);
        } catch (\Exception_Schoolbox $e) {
            $this->getResponse()->status->setCode(401);
            $response->set(array(
                'format' => 'json',
                'data'   => 'Invalid username or password'
            ));
            return;
        }

        $now = \Alaress_Date_Format::now();

        try {
            $registry = $this->registryMapper->findById($_SESSION['id'], $data['token'], $provider);
            $registry->setTouchedAt($now);

        } catch (MapperException $e) {
            /** @var Registry $registry */
            $registry = $this->registryMapper->createModel();
            $registry
                ->setUser($_SESSION['user'])
                ->setToken($data['token'])
                ->setProvider($provider)
                ->setCreatedAt($now)
                ->setTouchedAt($now);
        }

        $this->registryMapper->save($registry);

        $response->set(array(
            'format' => 'json',
            'data'   => array(
                'id'  => $_SESSION['id'],
                'key' => $_SESSION['key'],
            )
        ));
    }


    /**
     * @param array $array
     * @param array $keys
     */
    protected function assertArrayKeys(array $array, array $keys) {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $array)) {
                throw new \UnderflowException($key);
            }
        }
    }

} 