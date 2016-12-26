<?php
/**
 * Created by PhpStorm.
 * User: gtorres
 * Date: 14/04/16
 * Time: 14:59
 */

namespace Centralpos\ApiClient;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use ApiClient;

class RequestBuilder
{
    /**
     * @var string
     */
    protected $resource;
    
    /**
     * Modelo del cuál se instancia la clase
     * @var Model
     */
    protected $model;

    /**
     * Columnas a seleccionar en la consulta
     *
     * @var array
     */
    protected $fields = [];

    /**
     * Condiciones a utilizar en la consulta
     *
     * @var array
     */
    protected $wheres = [];

    /**
     * Relaciones a cargar
     *
     * @var array
     */
    protected $with = [];

    /**
     * Orden en que se solicitarán los resultados
     *
     * @var array
     */
    protected $sort;

    /**
     * Cantidad máxima de filas a obtener en la consulta
     *
     * @var int
     */
    protected $limit;

    /**
     * Cantidad de filas a ignorar en la consulta
     *
     * @var int
     */
    protected $offset;

    /**
     * Sufijos de operadores aceptados por la api
     *
     * @var array
     */
    protected $suffixes = [
        '=' => '',
        'like' => '-lk',
        'not like' => '-not-lk',
        'in' => '-in',
        'not in' => '-not-in',
        '>=' => '-min',
        '<=' =>'-max',
        '<' => '-st',
        '>' => '-gt',
        '!=' => '-not'
    ];

    /**
     * Operadores válidos
     *
     * @var array
     */
    protected $operators = [
        'like',
        'not like',
        'in',
        'not in',
        '>=',
        '<=',
        '<',
        '>',
        '!='
    ];

    /**
     *
     *
     * @var string
     */
    protected $customUrl;

    /**
     * @var \Requests_Response
     */
    protected $lastResponse;

    /**
     * @var array
     */
    protected $rawParams = [];

    /**
     * RequestBuilder constructor.
     * @param string|Model $resource
     */
    public function __construct($resource)
    {
        $this->setResource($resource);
    }

    /**
     * @param null|int $id
     * @return Model|\Requests_Response
     */
    public function get($id = null){

        return $this->runRequest($this->getParams(), $id);
    }

    /**
     * @param mixed $id
     * @param array $params
     * @return \Requests_Response
     */
    public function put($id, $params){

        return $this->runRequest($params, $id, "put");
    }

    /**
     * @param array $params
     * @return \Requests_Response
     */
    public function post($params){

        return $this->runRequest($params, null, "post");
    }

    /**
     * @param int $id
     * @return \Requests_Response
     */
    public function delete($id){

        return $this->runRequest([], $id, "delete");
    }

    /**
     * @param $columns
     * @return $this
     */
    public function select($columns)
    {
        $this->fields = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function take($limit){

        $this->limit = $limit;

        return $this;
    }

    /**
     * @param int $offset
     * @return $this
     */
    public function offset($offset){

        $this->offset = $offset;

        return $this;
    }

    /**
     * @param array|string $relations
     * @return RequestBuilder
     */
    public function with($relations){

        if(func_num_args() > 1){

            $relations = func_get_args();
        }

        if(is_array($relations)){

            $this->with = array_merge($this->with, $relations);
        }
        else{

            array_push($this->with, $relations);
        }
        
        return $this;
    }

    /**
     * @param array|string $columns
     * @param string $order
     * @return $this
     */
    public function orderBy($columns, $order = 'asc'){

        $orders = ['asc' => '', 'desc' => '-'];

        if(is_array($columns)){

            $columns = explode(',', $columns);
        }

        if(!in_array($order, array_flip($orders))){

            throw new \InvalidArgumentException("Orden inválido");
        }

        $this->sort = $orders[$order]."$columns";

        return $this;
    }

    /**
     * @return Model
     */
    public function first(){

        $this->take(1);

        $response = $this->api()->get($this->getResource(), $this->getParams());

        return $this->response($response, true);
    }

    /**
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @return $this
     */
    public function where($column, $operator = null, $value = null){

        if (func_num_args() == 2)
        {
            list($value, $operator) = array($operator, '=');
        }
        elseif ($this->invalidOperatorAndValue($operator, $value))
        {
            throw new \InvalidArgumentException("Value must be provided.");
        }

        $this->wheres[] = compact('column', 'operator', 'value');

        return $this;

    }

    /**
     * @param string $column
     * @param array $values
     * @return RequestBuilder
     */
    public function whereIn($column, $values){

        return $this->where($column, 'in', implode(',', $values));
    }

    /**
     * @param string $column
     * @param string $from
     * @param string $to
     * @return $this
     */
    public function whereBetween($column, $from, $to){

        return $this->where($column, '>=', $from)
                    ->where($column, '<=', $to);
    }

    /**
     * @param string $operator
     * @param string $value
     * @return bool
     */
    protected function invalidOperatorAndValue($operator, $value)
    {
        $isOperator = in_array($operator, $this->operators);

        return ($isOperator && $operator != '=' && is_null($value));
    }

    /**
     * @param string $method
     * @param mixed $id
     * @param  array $params
     * @return \Requests_Response|Model
     */
    protected function runRequest($params = [], $id = null, $method = "get"){

        $resource = $this->getResource($id);

        $this->lastResponse = $this->api()->$method($resource, $params);

        return $this->response($this->lastResponse);

    }

    /**
     * @return \Centralpos\ApiClient\ApiClient
     */
    protected function api(){
        
        $connection = $this->model->getConnection();
        
        return ApiClient::connection($connection);
    }

    /**
     * @param \Requests_Response $response
     * @param bool $is_first
     * @return array
     */
    protected function response($response, $is_first = false){

        if(method_exists($this->model, 'result')){

            return $this->model->result($response, $is_first);
        }

        return $response;
    }

    /**
     * @param string|Model $resource
     */
    protected function setResource($resource){

        if($resource instanceof Model){

            $this->model = $resource;
            $this->resource = $resource->getResource();
        }
        else{

            $this->resource = $resource;
        }

    }

    /**
     * @param string $id
     * @return string
     */
    protected function getResource($id = null){

        $resource = $this->resource;

        $resource .= !empty($this->customUrl) ? "/$this->customUrl" : '';

        $resource .= !empty($id) ? "/$id" : '';

        return $resource;
    }
    /**
     * @param array $params
     * @param mixed $id
     * @param string $method
     * @return Model|\Requests_Response
     */
    public function raw($params = [], $id = null, $method = 'get'){

        return $this->runRequest($params, $id, $method);
    }

    /**
     * @param string $url
     * @return $this
     */
    public function customUrl($url){

        $this->customUrl = $url;

        return $this;
    }
    /**
     * @return array
     */
    protected function getParams(){

        $params = [];
        $vars = ['fields', 'limit', 'offset', 'with', 'sort'];

        foreach($vars as $var){

            if(is_array($this->$var) && count($this->$var)){

                $params["_$var"] = implode(',', $this->$var);

            }
            elseif(isset($this->$var)){

                $params["_$var"] = $this->$var;
            }
        }

        return array_merge($params, $this->getWheres(), $this->rawParams);

    }

    /**
     * @return array
     */
    protected function getWheres(){

        $wheres = [];

        foreach ($this->wheres as $where){

            $column = $this->addSuffix($where['column'], $where['operator']);

            $wheres[$column] =  $where['value'];
        }

        return $wheres;
    }

    /**
     * @param string $column
     * @param string $operator
     * @return string
     */
    protected function addSuffix($column, $operator){

        $suffix = $this->suffixes[$operator];

        return "$column$suffix";
    }

    /**
     * @param $count
     * @param callable $callback
     * @return bool
     */
    public function chunk($count, callable $callback)
    {
        $results = $this->forPage($page = 1, $count)->get();

        while (count($results) > 0) {
            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if (call_user_func($callback, $results) === false) {
                return false;
            }

            $page++;

            $results = $this->forPage($page, $count)->get();
        }

        return true;
    }

    /**
     * @param $page
     * @param int $perPage
     * @return mixed
     */
    public function forPage($page, $perPage = 15)
    {
        return $this->offset(($page - 1) * $perPage)->take($perPage);
    }

    /**
     * @param int $perPage
     * @param string $pageName
     * @param null $page
     * @return LengthAwarePaginator
     */
    public function paginate($perPage = 15, $pageName = 'page', $page = null)
    {
        $this->setRawParams(['_config' => 'meta-filter-count']);
        
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $results = $this->forPage($page, $perPage)->get();

        $total = $this->lastResponse->headers['Meta-Filter-Count'];

        return new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setRawParams($params){

        $this->rawParams = array_merge($this->rawParams, $params);
        
        return $this;
    }

}