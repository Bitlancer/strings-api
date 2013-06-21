<?php

namespace Application;

class Formation extends ResourceModel
{
    public function exists($id){

        $query = "
            SELECT id
            FROM formation
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
            FROM formation
            WHERE formation.id = :id
        ";

        $queryParameters = array(
            ':id' => $id
        );

        $entity = $this->fetch($query,$queryParameters);
        if($entity === false)
            return false;

        return $entity;
    }

    public function delete($id){

        $query = "
            DELETE FROM formation
            WHERE formation.id = :id
        ";

        $queryParameters = array(
            ':id' => $id
        );
            
        $this->query($query,$queryParameters);
    }

    public function updateStatus($formationId,$status){

        $query = "
            UPDATE formation
            SET status = :status
            WHERE id = :id
        ";

        $queryParameters = array(
            ':status' => $status,
            ':id' => $formationId
        );

        $this->query($query,$queryParameters);
    }
}
