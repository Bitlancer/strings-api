<?php

namespace APIFramework;

class DatabaseHandler extends \PDO {

    public function __construct($datasource,$host,$database,$login,$password,$charset='UTF-8'){

        $PDOStr = $datasource . ":" .
            "host=" . $host . ";" .
            "dbname=" . $database . ";" .
            "charset=" . $charset;

        parent::__construct($PDOStr,$login,$password,self::getDefaultConnectionOptions());
    }

    public static function getDefaultConnectionOptions(){

        return array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_FETCH_TABLE_NAMES => true,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,

        );
    }
}
