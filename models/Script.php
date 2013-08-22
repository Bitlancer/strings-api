<?php

namespace Application;

class Script extends ResourceModel
{

    public function exists($id){

        $query = "
            SELECT id
            FROM script
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
            FROM script
            LEFT JOIN application ON script.model = 'Application' AND
                script.foreign_key_id = application.id
            LEFT JOIN formation ON script.model = 'Formation' AND
                script.foreign_key_id = formation.id
            WHERE script.id = :id
        ";

        $queryParameters = array(
            ':id' => $id
        );

        $entity = $this->fetch($query,$queryParameters);
        return $entity;
    }
}
