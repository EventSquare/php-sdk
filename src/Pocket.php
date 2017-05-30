<?php

namespace EventSquare;

use EventSquare\EventSquareException;
use EventSquare\Connection;

class Pocket {

    private $connection;
    private $request;
    private $data;

    public function __construct(Connection $connection, $uid) {
        $this->connection = $connection;
        $this->uid = $uid;
    }

    public function tickets() {
        return $this->connection->send('collect/' . $this->uid . '/tickets','pocket',$this);
    }

    public function append($data) {
        $this->data = $data;
        return $this;
    }

    public function data() {
        return $this->data;
    }

}
