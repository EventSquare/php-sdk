<?php

namespace EventSquare;

use EventSquare\EventSquareException;

class EventSquare
{
    private $headers = [];
    private $endpoint = 'http://api.eventsquare.co/1.0';

    private $queueid;
    private $cartid;

    private $timezone;
    private $language = null;
    private $store;
    private $expires_at;

    /**
    * Setup EventSquare library
    */
    public static function init($apikey)
    {
        $eventsquare = new EventSquare;
        $eventsquare->checkDependencies();
        $eventsquare->headers['apikey'] = $apikey;
        return $eventsquare;
    }

    /**
    * Override endpoint
    */
    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    /**
    * Get endpoint;
    */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
    * Set timezone;
    */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
        return $this;
    }

    /**
    * Get timezone;
    */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
    * Set language;
    */
    public function setLanguage($language)
    {
        $this->language = $language;
        return $this;
    }

    /**
    * Set store;
    */
    public function setStore($event,$edition=null,$channel=null,$lang=null)
    {

        $uri = $event;
        if($edition) {
            $uri .= '/' . $edition;
        }
        if($channel) {
            $uri .= '/' . $channel;
        }

        $parameters = [
            'agent' => $_SERVER['HTTP_USER_AGENT'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'cart' => $this->getCartId(),
            'queue' => $this->getQueueId(),
            'referer' => null,
            'language' => $this->language,
        ];

        if(!empty($_SERVER['HTTP_REFERER'])){
            $parameters['referer'] = $_SERVER['HTTP_REFERER'];
        }

        $this->store = $this->get('/store/'.$uri,$parameters)->body;
        $this->updateQueueId();
        $this->updateCartId();
        return $this;
    }

    /**
    * Get store;
    */
    public function getStore()
    {
        return $this->store;
    }

    /**
    * Has store;
    */
    public function hasStore()
    {
        if($this->store) return true;
        return false;
    }

    /**
    * Check if we the store is open for public
    */
    public function isClosed()
    {
        return false;
    }

    /**
    * Check if this store requires a password
    */
    public function isLocked()
    {
        return false;
    }

    /**
    * Check if we are queued
    */
    public function isQueue()
    {
        if($this->hasStore() && !empty($this->getStore()->edition->queue)) return true;
        return false;
    }

    /**
    * Get queueid
    */
    public function getQueueId()
    {
        return $this->queueid?: null;
    }

    /**
    * Set queueid
    */
    public function setQueueId($queueid)
    {
        $this->queueid = $queueid;
        return $this;
    }

    /**
    * Update queueid
    */
    public function updateQueueId()
    {
        if(!empty($this->store->edition->queue->queueid))
        {
            $this->queueid = $this->store->edition->queue->queueid;
        }
        return $this;
    }

    /**
    * Check if we have a cart
    */
    public function isCart()
    {
        if($this->hasStore() && !empty($this->getStore()->edition->cart)) return true;
        return false;
    }

    /**
    * Find cartid in store and update instance property
    */
    public function getCartId()
    {
        return $this->cartid?: null;
    }

    /**
    * Set cartid
    */
    public function setCartId($cartid)
    {
        $this->cartid = $cartid;
        return $this;
    }

    /**
    * Update cartid
    */
    public function updateCartId()
    {
        if(!empty($this->store->edition->cart->cartid))
        {
            $this->cartid = $this->store->edition->cart->cartid;
        }
        return $this;
    }

    /**
    * Get edition;
    */
    public function getEdition($uid=null)
    {
        return !empty($this->store->edition) ? $this->store->edition : null;
    }

    /**
    * Get cart expiration time;
    */
    public function getCart()
    {
        return !empty($this->store->edition->cart) ? $this->store->edition->cart : null;
    }

    /**
    * Methods
    */
    public function get($method,$params=[]) {
        return $this->compile('get',$method,$params);
    }
    public function post($method,$params=[]) {
        return $this->compile('post',$method,$params);
    }
    public function put($method,$params=[]) {
        return $this->compile('put',$method,$params);
    }
    public function delete($method,$params=[]) {
        return $this->compile('delete',$method,$params);
    }

    /**
    * Compile
    */
    private function compile($type,$method,$params=[])
    {
        $method = $this->endpoint.$method;
        $headers = [];
        foreach($this->headers as $key=>$value)
            $headers[] = $key.': '.$value;
        return $this->cURL($type,$method,$headers,$params);
    }

    /**
    * cURL request
    */
    private function cURL($type,$method,$headers,$params=[])
    {

        if($type=='get')
            $method = $method.'?'.$this->bindPostFields($params);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $method);
        curl_setopt($curl, CURLOPT_VERBOSE, 0);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,20);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($type));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $this->bindPostFields($params));

        $response = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

        // Service unavailable
        $code = curl_getinfo($curl,CURLINFO_HTTP_CODE) > 0 ? curl_getinfo($curl,CURLINFO_HTTP_CODE) : 503;
        $body = substr($response, $header_size);
        curl_close($curl);

        try {
            $body = (object)json_decode($body);
        } catch(\Exception $e) {
            throw new \Exception('There was a problem parsing the API response data',500);
        }

        if(!empty($body->error)){
            throw new EventSquareException($body->code, $body->error, $body->message);
        }

        if($code != 200) {
            throw new \Exception('A problem occured when trying to connect to the API',500);
        }

        return (object) [
            'code' => $code,
            'body' => $body,
        ];
    }

    /**
     * Bind post fields
     */
    private function bindPostFields($params)
    {
        return http_build_query($params);
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
