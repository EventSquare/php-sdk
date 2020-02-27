<?php

namespace EventSquare;

use EventSquare\EventSquareException;
use EventSquare\Connection;

class Store {

    private $connection;
    private $data;

    private $preview_token;
    private $needs_password;
    private $password;

    private $cartid;
    private $access_token;
    private $entry_url;

    private $expires_at;

    public function __construct(Connection $connection) {
        $this->connection = $connection;
    }

    public $event = null;
    public $edition = null;
    public $channel = null;

    /**
    * Set language;
    */
    public function setLanguage($language)
    {
        $this->language = $language;
        return $this;
    }

    /**
    * Get language;
    */
    public function getLanguage()
    {
        return $this->language;
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
    * Set needs password;
    */
    public function setNeedsPassword($needs_password)
    {
        $this->needs_password = $needs_password;
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
            $link .= '?preview_token=' . $segments['preview_token'];
        }

        if(!$segments){
            if($this->edition) {
                $link .= '/' . $this->edition->uri;
                if($this->edition->channel) {
                    $link .= '/' . $this->edition->channel->uri;
                }
            }
            if($this->preview_token) {
                $link .= '?preview_token=' . $this->preview_token;
            }
        }
        return ltrim($link, '/');
    }

    /**
    * Set event;
    */
    public function event($event,$cache = null)
    {
        if($cache){
            $this->event = $cache;
        } else {
            $parameters = [
                'language' => $this->language
            ];
            $parameters = array_merge($this->connection->meta,$parameters);
            $this->event = $this->connection->send('store/' . $event,'event')->get($parameters);
        }
        
        return $this->event;
    }

    /**
    * Set event;
    */
    public function load($event,$edition,$channel,$preview_token = null)
    {
        $uri = $event.'/'.$edition;

        if($channel) {
            $uri .= '/' . $channel;
        }

        $parameters = [
            'access_token' => $this->getAccessToken(),
            'cart' => $this->getCartId(),
            'entry_url' => $this->getEntryUrl(),
            'language' => $this->language,
            'preview_token' => $preview_token,
        ];

        $parameters = array_merge($this->connection->meta,$parameters);

        //Append extra headers
        $headers = [];
        if($password = $this->getPassword()){
            $headers['password'] = $password;
        }

        $this->preview_token = $preview_token;
        $this->edition = $this->connection->withHeaders($headers)->send('store/' . $uri,'edition')->get($parameters);

        $this->updateCartId();
        return $this;
    }

    /**
    * Get cart
    */
    public function getCart()
    {
        $this->cart = $this->connection->send('cart/' . $this->getCartId(),'cart')->get([
            'language' => $this->language
        ]);
        return $this->cart;
    }
    
    /**
    * Set Access Token
    */
    public function setAccessToken($access_token)
    {
        $this->access_token = $access_token;
        return $this;
    }

     /**
    * Find access token in store and update instance property
    */
    public function getAccessToken()
    {
        return $this->access_token?: null;
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
    * Clear cartid
    */
    public function clearCartId()
    {
        $this->cartid = null;
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
    * Set password
    */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    /**
    * Get password
    */
    public function getPassword()
    {
        return $this->password?: null;
    }

    /**
    * Get entry_url
    */
    public function getEntryUrl()
    {
        return $this->entry_url?: null;
    }

    /**
    * Set entry_url
    */
    public function setEntryUrl($entry_url)
    {
        $this->entry_url = $entry_url;
        return $this;
    }

    /**
    * Check if we the store is open for public
    */
    public function isClosed()
    {
        return !$this->event->editions;
        //return !$this->event->editions || !$this->channel;
    }

    /**
    * Check if the channel is locked
    */
    public function isLocked()
    {
        return $this->needs_password;
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
    * Get queue
    */
    public function getQueue()
    {
        return $this->edition->queue;
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
    * Check if the current cart is pending
    */
    public function isPending()
    {
        if(!empty($this->cart) && !empty($this->cart->pending)) return true;
        return false;
    }

    /**
    * Update cart type
    */
    public function updateType($uid,$show,$seatmap,$quantity,$places,$deal)
    {
        $parameters = [
            'quantity' => $quantity
        ];

        if($show){
            $parameters['show'] = $show;
        }
        if($seatmap){
            $parameters['seatmap'] = $seatmap;
        }
        if($places){
            $parameters['places'] = $places;
        }
        if($deal){
            $parameters['deal'] = $deal;
        }

        $this->connection->send('cart/' . $this->getCartId() . '/types/' . $uid)->put($parameters);
        return;
    }

    /**
    * Remove cart type
    */
    public function removeType($uid,$show,$seatmap,$voucher)
    {
        $parameters = [];

        if($show){
            $parameters['show'] = $show;
        }
        if($seatmap){
            $parameters['seatmap'] = $seatmap;
        }
        if($voucher){
            $parameters['voucher'] = $voucher;
        }

        $this->connection->send('cart/' . $this->getCartId() . '/types/' . $uid)->delete($parameters);
        return;
    }

    /**
    * Get show
    */
    public function getShow($event,$edition,$channel,$show_id)
    {
        $show = $this->connection->send('store/'.$event . '/' . $edition . '/' . $channel . '/' . $show_id,'show')->get([
            'cart' => $this->getCartId(),
            'language' => $this->language
        ]);
        return $show;
    }

    /**
    * Get seatmap
    */
    public function getSeatmap($event,$edition,$channel,$show_id,$seatmap_id)
    {
        $show = $this->connection->send('store/'.$event . '/' . $edition . '/' . $channel . '/' . $show_id . '/' . $seatmap_id,'seatmap')->get([
            'cart' => $this->getCartId(),
            'language' => $this->language
        ]);
        return $show;
    }

    public function getSeatmapDetails($seatmap_id)
    {
        $seatmap = $this->connection->send('seatmap/'.$seatmap_id,'seatmap')->get([
            'language' => $this->language
        ]);
        return $seatmap;
    }


}
