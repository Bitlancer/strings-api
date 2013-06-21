<?php

namespace Application;

class Device extends ResourceModel
{

    public function exists($id){

        $query = "
            SELECT id
            FROM device
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
            FROM device
            JOIN device_type ON device.device_type_id = device_type.id
            WHERE device.id = :id
        ";
        $queryParameters = array(
            ':id' => $id
        );

        $entity = $this->fetch($query,$queryParameters);
        if($entity === false)
            return false;

        $query = "
            SELECT * 
            FROM device_attribute
            WHERE device_id = :device_id
        ";
        $queryParameters = array(
            ':device_id' => $id
        );

        $attrs = $this->fetchAll($query,$queryParameters);
        if($attrs === false)
            $attrs = array();
        $attrs = array('device_attribute' => $this->toKeyValue($attrs,'device_attribute.var','device_attribute.val'));

        $entity = array_merge($entity,$attrs);

        return $entity;
    }

    public function getByFormationId($formationId){

        $query = "
            SELECT *
            FROM device
            JOIN device_type ON device.device_type_id = device_type.id
            WHERE device.formation_id = :formationId
        ";

        $queryParameters = array(
            ':formationId' => $formationId
        );

        return $this->fetchAll($query,$queryParameters);
    }

    public function saveAttribute($device,$var,$val){

        $deviceId = $device['device.id'];
        $organizationId = $device['device.organization_id'];
        
        return parent::saveAttribute('device_attribute','device_id',$organizationId,$deviceId,$var,$val);
    }

    public function updateImplementationStatus($device,$status){

        $this->saveAttribute($device,'implementation.status',$status);
        $this->saveAttribute($device,'implementation.status.last_updated',date('Y-m-d H:i:s'));
    }

    public function updateStringsStatus($device,$status){

        $query = "
            UPDATE device
            SET status = :status
            WHERE id = :id
        ";

        $queryParameters = array(
            ':status' => $status,
            ':id' => $device['device.id']
        );

        return $this->query($query,$queryParameters);
    }

    public function delete($id){

        $query = "
            DELETE d,da
            FROM device as d
            JOIN device_attribute as da on d.id = da.device_id
            WHERE d.id = :id
        ";

        $queryParameters = array(
            ':id' => $id
        );

        return $this->query($query,$queryParameters);
    }

    public function setStatusByProviderId($status,$devices){

        if(!is_array($devices))
            $devices = array($devices);

        $query = "
            UPDATE device_attribute as da
            LEFT JOIN device_attribute as da2 ON da.device_id = da2.device_id
            SET da.val = ?
            WHERE da.var = 'implementation.status' AND
                da2.var = 'implementation.id' AND
                da2.val IN (" . implode(',',array_fill(0,count($devices),'?')) . ")";

        $queryParameters = array_merge(array($status),$devices);

        $this->query($query,$queryParameters);
    }

    public function saveIPs($device,$ips){
        foreach($ips as $network => $networkIps){
            foreach($networkIps as $version => $address){
                $network = str_replace(' ','_',$network);
                $network = str_replace('.','',$network);
                $this->saveAttribute($device,"implementation.address.$network.$version",$address);
            }
        }
    }
}
