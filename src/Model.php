<?php
/**
 * Created by PhpStorm.
 * User: gtorres
 * Date: 18/03/16
 * Time: 14:33
 */

namespace Centralpos\ApiClient;

use Illuminate\Support\Collection;

class Model extends \Jenssegers\Model\Model
{
    /**
     * @var null|string
     */
    protected $connection = null;
    /**
     * @var string
     */
    protected $resource;
    /**
     * @var string
     */
    protected $resource_name;
    /**
     * @var string
     */
    protected $plural_resource_name;
    /**
     * @var array
     */
    protected $relations = [];
    /**
     * @var bool
     */
    private $exists = false;

    /**
     * @param $id
     * @return $this
     */
    public static function find($id){

        $instance = new static();

        return $instance->newRequest()
                        ->get($id);

    }

    /**
     * @param array $attr
     * @return array
     */
    public static function create(array $attr){

        $instance = new static();

        return $instance->newRequest()
                        ->post($attr);
    }

    /**
     * @return $this
     */
    public function save(){

        if(!$this->exists){

            self::create($this->attributes);

            $this->exists = true;

        }else{

            self::update($this->id, $this->attributes);
        }

        return $this;
    }

    /**
     * @param $id
     * @param array$attributes
     * @return array
     */
    public static function update($id, $attributes){

        $instance = new static();

        return $instance->newRequest()
                        ->put($id, $attributes);

    }

    /**
     * @param array $resources
     * @return Collection
     */
    protected function makeCollection($resources){

        $models = [];

        foreach($resources as $resource){

            $models[] = $this->fillModel($resource);
        }

        return new Collection($models);
    }

    /**
     * @param array $attributes
     * @return mixed
     */
    protected function createModel($attributes){

        $model_name = get_called_class();

        $model = new $model_name($attributes);

        $model->exists = true;

        return $model;
    }

    /**
     * @param array $attributes
     * @return static
     */
    public function fillModel($attributes){

        $relateds = $this->filterRelateds($attributes);

        if(count($relateds) > 0){

            $attributes = $this->convertRelations($relateds, $attributes);
        }

        return $this->createModel($attributes);
    }

    /**
     * @param array $relateds
     * @param array $attributes
     * @return array
     */
    protected function convertRelations($relateds, $attributes){

        foreach($relateds as $related){

            if(count($attributes[$related]) > 0){

                $attributes[$related] = $this->makeRelated($related, $attributes[$related]);
            }

        }

        return $attributes;

    }

    /**
     * @param string $name
     * @param array $attributes
     * @return array|Model
     */
    protected function makeRelated($name, $attributes){

        $class = $this->relations[$name];

        if(is_array(current($attributes))){

            $related_models = [];

            foreach($attributes as $related_attributes){

                $related_models[] = $this->fillRelatedModel($class, $related_attributes);

            }

            return collect($related_models);
        }
        else{

            return $this->fillRelatedModel($class, $attributes);
        }
    }

    /**
     * @param string $class
     * @param array $attributes
     * @return Model
     */
    protected function fillRelatedModel($class, $attributes){

        $model = new $class();

        return $model->fillModel($attributes);
    }
    /**
     * @param array $attributes
     * @return array
     */
    protected function filterRelateds($attributes){

        $relations = array_keys($this->relations);

        $relateds = [];

        foreach($attributes as $col => $value){

            $related = $this->filterRelation($col);

            if(in_array($related, $relations)){

                $relateds[] = $related;

            }
        }

        return $relateds;
    }

    /**
     * @param string $col
     * @return string
     */
    protected function filterRelation($col){

        $related = explode(".", $col);

        return $related[0];
    }

    /**
     * @param \Requests_Response $response
     * @throws \Exception
     */
    protected function error($response){

        $errors = ['status_code' => $response->status_code];

        $json = json_decode($response->body, true);

        if(is_array($json) && array_key_exists('errors', $json)){

            $errors = array_merge($errors, $json);
        }

        throw new \Exception(json_encode($errors));
    }

    /**
     * @param \Requests_Response $response
     * @param bool $first
     * @return array
     * @throws \Exception
     */
    public function result($response, $first = false){

        if($response->success){

            return $this->format_response($response, $first);
        }

        $this->error($response);
    }

    /**
     * @param \Requests_Response $response
     * @param bool $first
     * @return Collection|$this|array
     */
    protected function format_response($response, $first = false){

        $json = json_decode($response->body, true);

        if(array_key_exists($this->plural_resource_name, $json)){

            $data = $json[$this->plural_resource_name];

            if($first && count($data) == 1){

                return $this->fillModel($data[0]);
            }

            return $this->makeCollection($json[$this->plural_resource_name]);
        }

        if(array_key_exists($this->resource_name, $json)){

            return $this->fillModel($json[$this->resource_name]);
        }

        return $json;
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $request = $this->newRequest();

        return call_user_func_array([$request, $method], $parameters);
    }

    /**
     * @return RequestBuilder;
     */
    public function newRequest(){

        return new RequestBuilder($this);

    }

    /**
     * @return string
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @return string
     */
    public function getConnection(){
        
        return $this->connection;
    }

}