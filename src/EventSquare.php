<?php

namespace EventSquare;

use EventSquare\EventSquareException;

class EventSquare
{
    private $headers = [];
    private $meta = [];
    private $endpoint = "http://api.eventsquare.co/1.0";

    private $preview_token;

    private $queueid;
    private $cartid;

    private $timezone;
    private $language = null;

    public $event;
    public $edition;
    public $channel;

    private $expires_at;

    /**
    * Setup EventSquare library
    */
    public static function init($apikey)
    {
        $eventsquare = new EventSquare;
        $eventsquare->checkDependencies();

        $eventsquare->headers['apikey'] = $apikey;

        $eventsquare->meta = [
            'agent' => $_SERVER['HTTP_USER_AGENT'],
            'ip' => $_SERVER['REMOTE_ADDR'],
        ];

        if(!empty($_SERVER['HTTP_REFERER'])){
            $eventsquare->meta['referer'] = $_SERVER['HTTP_REFERER'];
        }

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
    * Get language;
    */
    public function getLanguage()
    {
        return $this->language;
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
    * Get default language;
    */
    public function getDefaultLanguage()
    {
        return $this->event->languages[0];
    }

    /**
    * Set default language;
    */
    public function setDefaultLanguage()
    {
        $this->language = $this->getDefaultLanguage();
        return $this;
    }

    /**
    * See if currently set language is active in the event
    */
    public function acceptsActiveLanguage()
    {
        return in_array($this->language,$this->event->languages);
    }

    /**
    * Set event;
    */
    public function setEvent($event)
    {
        $parameters = [
            'language' => $this->language,
        ];

        $parameters = array_merge($this->meta,$parameters);

        $event = $this->get('/store/'.$event,$parameters)->body;
        $this->event = $event->event;

        return $this;
    }

    /**
    * Set store;
    */
    public function setEdition($event,$edition,$channel,$preview_token = null)
    {

        $uri = $event.'/'.$edition;

        if($channel) {
            $uri .= '/' . $channel;
        }

        $parameters = [
            'cart' => $this->getCartId(),
            'queue' => $this->getQueueId(),
            'language' => $this->language,
            'preview_token' => $preview_token,
        ];

        $this->preview_token = $preview_token;

        $parameters = array_merge($this->meta,$parameters);

        $edition = $this->get('/store/'.$uri,$parameters)->body;
        $this->edition = $edition->edition;

        if($channel) {
            $this->channel = $this->edition->channel;
        }

        $this->updateQueueId();
        $this->updateCartId();
        return $this;
    }

    /**
    * Get store;
    */
    public function getUri($segments=null)
    {
        $link = '';

        if(!empty($segments['domain']) && !empty($segments['event'])){
            $link = $segments['event'] . '.' . $segments['domain'];
        }

        if(!empty($segments['language'])){
            $link .= '/' . $segments['language'];
        }
        if(!empty($segments['edition'])){
            $link .= '/' . $segments['edition'];
        }
        if(!empty($segments['channel'])){
            $link .= '/' . $segments['channel'];
        }
        if(!empty($segments['preview_token'])){
            $link .= '/?token=' . $segments['preview_token'];
        }

        if(!$segments){

            if($this->edition) {
                $link .= '/' . $this->edition->uri;
            }
            if($this->channel) {
                $link .= '/' . $this->channel->uri;
            }
            if($this->preview_token) {
                $link .= '/?token=' . $this->preview_token;
            }

        }

        return ltrim($link, '/');
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
    * Check if we the store is open for public
    */
    public function isClosed()
    {
        return is_null($this->edition);
    }

    /**
    * Check if we are queued
    */
    public function isQueue()
    {
        if(!empty($this->edition->queue)) return true;
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
        if(!empty($this->edition->queue->queueid))
        {
            $this->queueid = $this->edition->queue->queueid;
        }
        return $this;
    }

    /**
    * Check if we have a cart
    */
    public function isCart()
    {
        if(!empty($this->edition->cart)) return true;
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
        if(!empty($this->edition->cart->cartid))
        {
            $this->cartid = $this->edition->cart->cartid;
            $this->getCart();
        }
        return $this;
    }

    /**
    * Check if the current cart is pending
    */
    public function isPending()
    {
        if(!empty($this->cart) && !empty($this->cart->pending)) return true;
        return false;
    }


    /**
    * Get cart
    */
    public function getCart()
    {
        $parameters = [
            //
        ];

        $parameters = array_merge($this->meta,$parameters);

        $response = $this->get('/cart/'.$this->getCartId(),$parameters)->body;
        $this->cart = $response->cart;

        return $this->cart;
    }

    /**
    * Update cart type
    */
    public function modifyCartType($uid,$quantity)
    {
        $parameters = [
            'quantity' => $quantity
        ];
        $parameters = array_merge($this->meta,$parameters);
        $this->put('/cart/'.$this->getCartId().'/types/'.$uid,$parameters)->body;

        return;
    }

    //return app('store')->getCartId();
    //Route::put(version().‘/cart/{cart}/types/{tuid}‘, ‘CartController@modifyType’);
    //quantity
    //timeslot

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
            throw new \Exception('A problem occured when trying to connect to the API',$code);
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
