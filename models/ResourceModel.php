<?php

namespace Application;

class ResourceModel extends Model
{

    protected function saveAttribute($table,$foreignKey,$organizationId,$modelId,$var,$val){

        $query = "
            SELECT id,val
            FROM $table
            WHERE $foreignKey = :model_id AND
                var = :var AND
                organization_id = :organization_id
        ";

        $queryParameters = array(
            ':model_id' => $modelId,
            ':var' => $var,
            ':organization_id' => $organizationId
        );

        $attr = $this->fetch($query,$queryParameters);

        if(empty($attr)){

           $query = "
                INSERT INTO $table
                (organization_id,$foreignKey,var,val)
                VALUES (:organization_id,:model_id,:var,:val)
            ";

            $queryParameters = array(
                ':organization_id' => $organizationId,
                ':model_id' => $modelId,
                ':var' => $var,
                ':val' => $val
            );

            $this->query($query,$queryParameters);
        }
        elseif($attr[$table.'.val'] != $val){

            $query = "
                UPDATE $table
                SET val = :val
                WHERE id = :id
            ";

            $queryParameters = array(
                ':val' => $val,
                ':id' => $attr[$table.'.id']
            );

            $this->query($query,$queryParameters);
        }
        else {
            return;
        }
    }

    protected function toKeyValue($array,$keyIndex,$valIndex=false){

        $keyValArray = array();
        foreach($array as $item){
            if($valIndex === false)
                $keyValArray[$item[$keyIndex]] = $item;
            else
                $keyValArray[$item[$keyIndex]] = $item[$valIndex];
        }

        return $keyValArray;
    }

    protected function fetch($query,$queryParameters=array()){

        $s = $this->query($query,$queryParameters);
        return $s->fetch();
    }

    protected function fetchAll($query,$queryParameters=array(),$index=false,$callback=false){

        $s = $this->query($query,$queryParameters);
        return $s->fetchAll();
    }

    protected function query($query,$queryParameters=array()){

        $query = trim($query);  //For DBA's sake :)

        $statement = $this->db->prepare($query);
        $statement->execute($queryParameters);
        return $statement;
    }
}
