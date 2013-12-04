<?php

namespace Application;

class Application extends ResourceModel
{
    public function exists($id){

        $query = "
            SELECT id
            FROM application
            WHERE id = :id
        ";

        $queryParameters = array(
            ':id' => $id
        );

        if($this->fetch($query,$queryParameters) === false)
            return false;
        else
            return true;
    } 

    public function get($id){

        $query = "
            SELECT *
            FROM application
            WHERE application.id = :id
        ";

        $queryParameters = array(
            ':id' => $id
        );

        $entity = $this->fetch($query,$queryParameters);
        if($entity === false)
            return false;

        return $entity;
    }
}
