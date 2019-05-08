<?php

namespace EventSquare;

use EventSquare\EventSquareException;

class Connection {

    public $endpoint = "http://api.eventsquare.co/1.0";

    private $apikey;
    private $uri;
    private $target;
    public  $meta = [];
    private $headers = [];
    private $params = [];
    private $instance;

    public function __construct($apikey) {

        $this->apikey = $apikey;

        $this->meta = [
            'agent' => !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
            'ip' => $this->getIp(),
        ];

        if(!empty($_SERVER['HTTP_REFERER'])){
            $this->meta['referer'] = $_SERVER['HTTP_REFERER'];
        }

    }

    public function send($uri,$target = null,$instance = null) {
        $this->uri = $uri;
        $this->target = $target;
        $this->instance = $instance;
        return $this;
    }

    /**
    * Methods
    */
    public function get($params = []) {
        $this->params = $params;
        return $this->compile('get');
    }
    public function post($params = []) {
        $this->params = $params;
        return $this->compile('post');
    }
    public function put($params = []) {
        $this->params = $params;
        return $this->compile('put');
    }
    public function delete($params = []) {
        $this->params = $params;
        return $this->compile('delete');
    }

    /**
    * Append extra headers
    */
    public function withHeaders($headers = []) {
        $this->headers = array_merge($this->headers,$headers);
        return $this;
    }

    /**
    * Compile
    */
    private function compile($type)
    {
        $method = $this->endpoint . '/' . $this->uri;
        $headers = [];
        $headers[] = 'apikey: ' . $this->apikey;
        foreach($this->headers as $key=>$value)
            $headers[] = $key.': '.$value;
        return $this->cURL($type,$method,$headers,$this->params);
    }

    /**
    * cURL request
    */
    private function cURL($type,$method,$headers,$params=[])
    {

        if($type=='get'){
            $method = $method.'?'.$this->bindPostFields($params);
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $method);
        curl_setopt($curl, CURLOPT_VERBOSE, 0);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 600);
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

        if($this->instance && $this->target && !empty($this->target) && !empty($body->{$this->target})){
            return $this->instance->append($body->{$this->target});
        }

        if($this->target){
            return $body->{$this->target};
        }
        return $body;
    }

    /**
     * Bind post fields
     */
    private function bindPostFields($params)
    {
        return http_build_query($params);
    }

    private function getIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP']))
        {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED']))
        {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        }
        elseif (!empty($_SERVER['HTTP_FORWARDED_FOR']))
        {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        }
        elseif (!empty($_SERVER['HTTP_FORWARDED']))
        {
            $ip = $_SERVER['HTTP_FORWARDED'];
        }
        else if (!empty($_SERVER['REMOTE_ADDR']))
        {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        else
        {
            $ip = null;
        }

        return $ip;
    }
}
