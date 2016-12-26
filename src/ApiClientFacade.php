<?php

namespace Centralpos\ApiClient;

use Illuminate\Support\Facades\Facade;

class ApiClientFacade extends Facade {

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'apiclient'; }

}
