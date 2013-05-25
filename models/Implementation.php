<?php

namespace Application;

class Implementation extends ResourceModel
{
    public function get($id){
        
        $query = "
            SELECT *
            FROM implementation
            JOIN provider ON provider.id = implementation.provider_id
            WHERE implementation.id = :id
        ";

        $queryParameters = array(
            ':id' => $id
        );
        
        $entity = $this->fetch($query,$queryParameters);
        if($entity === false)
            return false;

        $query = "
            SELECT * 
            FROM implementation_attribute
            WHERE implementation_id = :implementation_id
        ";

        $queryParameters = array(
            ':implementation_id' => $id
        );

        $attrs = $this->fetchAll($query,$queryParameters);
        if($attrs === false)
            $attrs = array();
        $attrs = array('implementation_attribute' => $this->toKeyValue($attrs,'implementation_attribute.var','implementation_attribute.val'));

        $entity = array_merge($entity,$attrs);

        return $entity;
    }
}
