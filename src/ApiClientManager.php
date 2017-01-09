<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Centralpos\ApiClient;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Cache\Repository as Cache;

class ApiClientManager{
    
    /**
     * @var array
     */
    protected $config;

    /**
     * @var array
     */
    protected $connections = [];

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * Api constructor.
     * @param Repository $config
     * @param Cache $cache
     */
    public function __construct(Repository $config, Cache $cache) {

        $this->config = $config;
        $this->cache = $cache;
    }

    /**
     * @param null|string $name
     * @return \Centralpos\ApiClient\ApiClient
     * @throws \Exception
     */
    public function connection($name = null){

        $name = $name ?: $this->getDefaultConnectionName();

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * @param string $name
     * @return ApiClient
     * @throws \Exception
     */
    protected function makeConnection($name){

        $config = $this->getConnectionConfig($name);

        return new ApiClient($config, $this->cache);
    }

    /**
     * @param string $name
     * @return array
     * @throws \Exception
     */
    public function getConnectionConfig($name){

        $connections = $this->config->get("api-client.connections");

        if (! is_array($config = array_get($connections, $name)) && !$config) {

            throw new \Exception("Connection [$name] not configured.");
        }

        return array_merge($config, compact('name'));
    }

    /**
     * @return string
     */
    public function getDefaultConnectionName(){

        return $this->config->get("api-client.default");
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->connection(), $method], $parameters);
    }
    
}
