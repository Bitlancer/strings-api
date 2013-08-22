<?php

namespace Application;

class JumpServer extends ResourceModel
{

    public function exists($id){

        $query = "
            SELECT id
            FROM jump_server
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
            FROM jump_server
            LEFT JOIN implementation ON 
                jump_server.implementation_id = implementation.id
            LEFT JOIN device ON jump_server.device_id = device.id
            WHERE jump_server.id = :id
        ";

        $queryParameters = array(
            ':id' => $id
        );

        $entity = $this->fetch($query,$queryParameters);
        return $entity;
    }

    public function getByImplementationAndRegion($implementationId,$regionId){

       $query = "
            SELECT *
            FROM jump_server
            LEFT JOIN implementation ON 
                jump_server.implementation_id = implementation.id
            LEFT JOIN device ON jump_server.device_id = device.id
            WHERE jump_server.implementation_id = :implementationId AND 
                jump_server.region = :regionId
        ";

        $queryParameters = array(
            ':implementationId' => $implementationId,
            ':regionId' => $regionId
        );

        $entity = $this->fetch($query,$queryParameters);
        return $entity;
    }
}
