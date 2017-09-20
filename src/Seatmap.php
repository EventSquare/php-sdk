<?php

namespace EventSquare;

use EventSquare\EventSquareException;
use EventSquare\Connection;

class Seatmap {

    private $connection;
    private $data;

    public function __construct(Connection $connection, $uid) {
        $this->connection = $connection;
        $this->uid = $uid;
    }

    public function details() {
        return $this->connection->send('seatmap/'.$this->uid,'seatmap',$this);
    }

    public function append($data) {
        $this->data = $data;
        return $this;
    }

    public function data() {
        return $this->data;
    }

}
