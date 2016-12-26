<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Centralpos\ApiClient;
/**
 * Description of ApiClient
 *
 * @author gtorres
 */

use Carbon\Carbon;
use Namshi\JOSE\JWS;
use Requests_Session;

class ApiClient{

    /**
     * @var string
     */
    protected $token;
    /**
     * @var array
     */
    protected $config;
    /**
     * @var Requests_Session
     */
    protected $session;

    /**
     * ApiClient constructor.
     * @param array $config
     */
    public function __construct(array $config) {

        $this->config = $config;
        $timeout = array_key_exists('timeout', $config) ? $config['timeout'] : 10;
        
        $this->session = new Requests_Session($config['url'], [], [], compact('timeout'));
    }

    /**
     * @throws \Exception
     */
    public function login(){

        $response = $this->session->post("auth/login", [], $this->config);

        if(! $response->success){

            throw new \Exception('No se pudo iniciar sesión.');
        }

        return $this->setToken($response);
    }

    /**
     * @return true
     * @throws \Exception
     */
    public function refreshToken(){

        $this->logout();

        return $this->login();
    }

    /**
     * @return Requests_Session
     */
    public function getSession(){

        if(! $this->hasSession()){

            $this->login();
        }

        return $this->session;
    }

    /**
     * @param $url
     * @param array $data
     * @param array $headers
     * @param array $options
     * @return \Requests_Response
     */
    public function get($url, $data = array(), $headers = array(), $options = array()) {

        return $this->getSession()->request($url, $headers, $data, 'GET', $options);
    }

    /**
     * @param $url
     * @param array $data
     * @param array $headers
     * @param array $options
     * @return \Requests_Response
     */
    public function put($url, $data = array(), $headers = array(), $options = array()) {

        return $this->getSession()->put($url, $headers, $data, $options);
    }

    /**
     * @param $url
     * @param array $data
     * @param array $headers
     * @param array $options
     * @return \Requests_Response
     */
    public function post($url, $data = array(), $headers = array(), $options = array()) {

        return $this->getSession()->post($url, $headers, $data, $options);
    }

    /**
     * @param $url
     * @param array $headers
     * @param array $options
     * @return \Requests_Response
     */
    public function delete($url, $headers = array(), $options = array()) {

        return $this->getSession()->delete($url, $headers, $options);
    }

    /**
     * @param \Requests_Response $response
     * @return true
     */
    protected function setToken(\Requests_Response $response){

        $json = json_decode($response->body);
        $this->token = $json->token;

        $this->session->headers['Authorization'] = 'Bearer '. $this->token;

        return true;
    }

    /**
     * @return string
     */
    public function getToken(){

        return $this->token;
    }

    /**
     * @return bool
     */
    public function hasSession(){

        return (! empty($this->token) && ! $this->sessionExpired());
    }

    /**
     *
     */
    public function sessionExpired(){

        if(! empty($this->token)){

            $jws = JWS::load($this->token);
            $payload = $jws->getPayload();
            $exp = Carbon::createFromTimestamp($payload['exp']);

            return $exp->lt(Carbon::now());
        }

        return true;
    }

    /**
     * @return mixed|\Requests_Response
     */
    public function logout(){

        return $this->get("auth/logout");
    }

    function __destruct()
    {
        $this->logout();
    }


}
