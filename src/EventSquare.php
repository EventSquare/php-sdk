<?php

namespace EventSquare;

use EventSquare\EventSquareException;
use EventSquare\Connection;
use EventSquare\Pocket;

class EventSquare
{

    private $connection;
    private $preview_token;

    private $queueid;
    private $cartid;

    private $timezone;
    private $language;

    public $event;
    public $edition;
    public $channel;

    private $expires_at;

    function __construct($options) {

        $this->checkDependencies();
        $this->connection = new Connection($options['api_key']);

        if($options['api_endpoint']){
            $this->connection->endpoint = $options['api_endpoint'];
        }

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
            'language' => $this->language
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
