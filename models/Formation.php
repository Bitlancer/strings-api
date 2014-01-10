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
            DELETE f, af, tf, tfs
            FROM formation as f
            LEFT JOIN application_formation as af on f.id = af.formation_id
            LEFT JOIN team_formation as tf on f.id = tf.formation_id
            LEFT JOIN team_formation_sudo as tfs on tf.id = tfs.team_formation_id
            WHERE f.id = :id
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
