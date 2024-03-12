<?php

namespace BigXLSX;

class SQLiteArray implements \ArrayAccess, \Countable{
    protected $data;

    public function __construct(){
        $this->data=new \PDO('sqlite::memory:');
        $this->data->exec('CREATE TABLE `data`(`k` "TEXT", `v` "BLOB", PRIMARY KEY(`k`))');
    }

    public static function isUsable(){
        return class_exists(\PDO::class) && in_array('sqlite', \PDO::getAvailableDrivers());
    }

    public function offsetExists($offset){
        $stmt=$this->data->prepare('SELECT 1 AS `e` FROM `data` WHERE `k`=:k');
        $stmt->bindValue(':k', $offset);
        if($stmt->execute() && false!==($stmt->fetchColumn(0))){
            return true;
        }
        return false;
    }

    public function offsetGet($offset){
        $stmt=$this->data->prepare('SELECT `v` FROM `data` WHERE `k`=:k');
        $stmt->bindValue(':k', $offset);
        if($stmt->execute() && false!==($res=$stmt->fetchColumn(0))){
            return unserialize($res);
        }
        return null;
    }

    public function offsetSet($offset, $value){
        $stmt=$this->data->prepare('INSERT OR REPLACE INTO `data`(`k`,`v`) VALUES (:k, :v)');
        $stmt->bindValue(':k', $offset);
        $stmt->bindValue(':v', serialize($value));
        $stmt->execute();
    }

    public function offsetUnset($offset){
        $stmt=$this->data->prepare('DELETE FROM `data` WHERE `k`=:k');
        $stmt->bindValue(':k', $offset);
        $stmt->execute();
    }

    public function count(){
        $stmt=$this->data->prepare('SELECT COUNT(*) FROM `data`');
        $res=$stmt->execute();
        if($stmt->execute() && false!==($res=$stmt->fetchColumn(0))){
            return intval($res);
        }
        return 0;
    }
}