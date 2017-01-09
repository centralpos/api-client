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
use Illuminate\Contracts\Cache\Repository as Cache;

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
     * @var Cache
     */
    protected $cache;

    /**
     * @var string
     */
    protected $cacheKey;

    /**
     * ApiClient constructor.
     * @param array $config
     * @param Cache $cache
     */
    public function __construct(array $config, Cache $cache) {

        $this->config = $config;
        $this->cache = $cache;
        $this->cacheKey = 'apiclient.'. $config['name'];

        $this->makeNewSession();
    }

    /**
     *  Crea una nueva instancia de la
     *  clase Requests_Session en session y si
     *  existe un token en la cache lo carga
     */
    protected function makeNewSession(){

        $timeout = array_key_exists('timeout', $this->config) ? $this->config['timeout'] : 10;

        $this->session = new Requests_Session($this->config['url'], [], [], compact('timeout'));

        if($token = $this->getTokenFromCache()){

            $this->setToken($token);
        }
    }

    /**
     * @return false|string
     */
    protected function getTokenFromCache(){

        return !$this->cache->has($this->cacheKey) ? false : $this->cache->get($this->cacheKey);
    }

    /**
     * @throws \Exception
     */
    public function login(){

        $response = $this->session->post("auth/login", [], $this->config);

        if(! $response->success){

            throw new \Exception('No se pudo iniciar sesión en '. $this->config['url']);
        }

        return $this->setNewToken($response);
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
    protected function setNewToken(\Requests_Response $response){

        $json = json_decode($response->body);

        $this->cache->put($this->cacheKey, $json->token, $this->tokenExpiration($json->token));
        $this->setToken($json->token);

        return true;
    }

    /**
     * @param string $token
     */
    protected function setToken($token){

        $this->token = $token;
        $this->session->headers['Authorization'] = 'Bearer '. $token;
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
     * @return boolean
     */
    public function sessionExpired(){

        if(! empty($this->token)){

            $tokenExpiration = $this->tokenExpiration($this->token);

            return $tokenExpiration->lt(Carbon::now());
        }

        return true;
    }

    /**
     * @param string $token
     * @return Carbon
     */
    public function tokenExpiration($token){

        $jws = JWS::load($token);
        $payload = $jws->getPayload();

        return Carbon::createFromTimestamp($payload['exp']);
    }

    /**
     * @return mixed|\Requests_Response
     */
    public function logout(){

        $this->cache->forget($this->cacheKey);
        return $this->get("auth/logout");
    }

}
