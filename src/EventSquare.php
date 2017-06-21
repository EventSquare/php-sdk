<?php

namespace EventSquare;

use EventSquare\EventSquareException;
use EventSquare\Connection;
use EventSquare\Store;
use EventSquare\Pocket;

class EventSquare
{

    private $connection;
    private $timezone;
    private $language;

    function __construct($options) {

        $this->checkDependencies();
        $this->connection = new Connection($options['api_key']);

        if($options['api_endpoint']){
            $this->connection->endpoint = $options['api_endpoint'];
        }

    }

    /**
    * Get event;
    */
    public function getEvent()
    {
        return !empty($this->event) ? $this->event : null;
    }

    /**
    * Check if edition
    */
    public function isEdition()
    {
        if($this->hasStore() && !empty($this->getStore()->edition)) return true;
        return false;
    }

    /**
    * Get edition;
    */
    public function getEdition($uid=null)
    {
        return !empty($this->store->edition) ? $this->store->edition : null;
    }

    /**
    * Get edition;
    */
    public function getEditions()
    {
        if(!empty($this->getEvent()->editions)){
            return $this->getEvent()->editions;
        } else {
            return [];
        }
    }

    /**
    * Store
    */
    public function store()
    {
        return new Store($this->connection);
    }

    /**
    * Pockets
    */
    public function pockets($uid)
    {
        return new Pocket($this->connection,$uid);
    }

    /**
    * Check Depencies for library
    * @return [type] [description]
    */
    private function checkDependencies()
    {
        if(!function_exists('curl_init'))
            die('cURL is not installed in your PHP installation.');
        return $this;
    }


}
