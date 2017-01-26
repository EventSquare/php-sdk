<?php

namespace EventSquare;

class EventSquare
{
    private $headers = [];
    private $endpoint = 'http://api.eventsquare.co/1.0';

    private $timezone;
    private $language = null;
    private $cartid;
    private $store;

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
            'cart' => $this->cartid,
            'referer' => null,
            'language' => $this->language,
        ];

        if(!empty($_SERVER['HTTP_REFERER'])){
            $parameters['referer'] = $_SERVER['HTTP_REFERER'];
        }

        $this->store = $this->get('/store/'.$uri,$parameters)->body;
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
    * Find cartid in store and update instance property
    */
    public function getCartId()
    {
        return !empty($this->store->edition->cart->cartid) ? $this->store->edition->cart->cartid : null;
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
        } catch (\Exception $e) {
            throw new \Exception($body);
        }

        if($code == 500 || $code == 503) {
            throw new \Exception($code . ': Problem with the API',$code);
        }

        if($code != 200) {
            throw new \Exception('Error while requesting store.',$code);
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
