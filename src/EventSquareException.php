<?php

namespace EventSquare;

class EventSquareException extends \Exception {

    private $error;

    public function __construct($code,$error,$message) {

        parent::__construct($message,$code);
        $this->code = $code;
        $this->error = $error;
        $this->message = $message;
    }

    public function message(){
        return $this->message;
    }
    public function error(){
        return $this->error;
    }
    public function code(){
        return $this->code;
    }

}
