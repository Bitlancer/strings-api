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

    public function exists($id){

        $query = "
            SELECT id
            FROM implementation
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

    public function getRegionName($implementationId,$regionId){

        $regions = $this->getOverridableAttribute($implementationId, 'regions');
        if($regions === false)
            return false;

        $regions = json_decode($regions,true);
        foreach($regions as $region){
            if($region['id'] == $regionId){
                return $region['name'];
            }
        }

        return false;
    }


    protected function getOverridableAttribute($implementationId, $attributeVar){

        //Attempt to grab the attribute from implementation_attribute
        $query = "
            SELECT val
            FROM implementation_attribute
            WHERE var = :var
        ";
        $queryParams = array(
            ':var' => $attributeVar
        );
        $result = $this->fetch($query,$queryParams);

        //If failed, grab the default attribute from provider_attribute
        if($result === false){

            $query = "
                SELECT val
                FROM provider_attribute
                WHERE var = :var
            ";
            $queryParams = array(
                ':var' => $attributeVar
            );

            $result = $this->fetch($query,$queryParams);

            if($result === false)
                return false;
        }

        return $result['provider_attribute.val'];
    }

}
