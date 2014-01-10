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
        $attrs = array('device_attribute' => $this->toKeyValue(
            $attrs,
            'device_attribute.var',
            'device_attribute.val'));

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

    public function getAttribute($deviceId,$attrName,$valOnly = true){

        $query = "
            SELECT *
            FROM device_attribute
            WHERE device_id = :deviceId AND
                var = :attrName
        ";

        $queryParameters = array(
            'deviceId' => $deviceId,
            'attrName' => $attrName
        );

        $attr = $this->fetch($query,$queryParameters);
        if(empty($attr))
            return false;

        if($valOnly)
            return $attr['device_attribute.val'];
        else
            return $attr;
    } 

    public function saveAttribute($device,$var,$val){

        $deviceId = $device['device.id'];
        $organizationId = $device['device.organization_id'];
        
        return parent::saveAttribute('device_attribute',
                                    'device_id',
                                    $organizationId,
                                    $deviceId,
                                    $var,
                                    $val);
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

    public function setCanSyncToLdapFlag($device,$canSync){

        $query = "
            UPDATE device
            SET can_sync_to_ldap = :canSync
            WHERE id = :id
        ";

        $queryParameters = array(
            ':canSync' => $canSync,
            ':id' => $device['device.id']
        );

        return $this->query($query,$queryParameters);
    }
 

    public function delete($id){

        //Delete the device and related models
        $query = "
            DELETE d,da,ds,h,td,tds
            FROM device as d
            LEFT JOIN device_attribute as da on d.id = da.device_id
            LEFT JOIN device_dns as ds ON d.id = ds.device_id
            LEFT JOIN hiera as h ON d.id = h.device_id
            LEFT JOIN team_device as td ON d.id = td.device_id
            LEFT JOIN team_device_sudo as tds ON td.id = tds.team_device_id
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

    public function getByFormationIdWithProfiles($formationId){

        //Get devices
        $query = "
            SELECT *
            FROM device
            JOIN device_type ON device.device_type_id = device_type.id
            JOIN device_attribute ON device.id = device_attribute.device_id
            JOIN role ON device.role_id = role.id
            WHERE device.formation_id = :formationId AND
                device_attribute.var = 'dns.internal.fqdn'
        ";
        $queryParameters = array(
            ':formationId' => $formationId
        );
        $devices = $this->fetchAll($query,$queryParameters);
        if(empty($devices))
            return false;

        //Get roles & profiles
        $query = "
            SELECT role.id,profile.*
            FROM role
            JOIN role_profile ON role.id = role_profile.role_id
            JOIN profile ON role_profile.profile_id = profile.id
            WHERE role.id IN (
                SELECT device.role_id
                FROM device
                WHERE device.formation_id = :formationId
            )
        ";
        $queryParameters = array(
            ':formationId' => $formationId
        );
        $rolesProfiles = $this->fetchAll($query,$queryParameters);

        //Group by role id
        $profilesGroupedByRoleId = array();
        foreach($rolesProfiles as $profile){
            $roleId = $profile['role.id'];
            if(isset($profilesGroupedByRoleId[$roleId]))
                $profilesGroupedByRoleId[$roleId][] = $profile;
            else
                $profilesGroupedByRoleId[$roleId] = array($profile);
        }
      
        $devicesWithProfiles = array();
        foreach($devices as $device){
            $roleId = $device['device.role_id'];
            $device['profiles'] = $profilesGroupedByRoleId[$roleId];
            $devicesWithProfiles[] = $device;
        }

        return $devicesWithProfiles;
    }

    public function getByApplicationIdWithProfiles($applicationId){

        //Get a list of formations from the appplication
        $query = "
            SELECT formation_id
            FROM application_formation
            WHERE application_formation.application_id = :applicationId
        ";
        $queryParameters = array(
            ':applicationId' => $applicationId
        );
        $results = $this->fetchAll($query,$queryParameters);
        $formationIds = array();
        foreach($results as $row){
            $formationIds[] = $row['application_formation.formation_id'];
        }

        //Call getByFormationIdWithProfiles
        //Not effecient - should refactor at some point
        $devices = array();
        foreach($formationIds as $formationId){
            $devices = array_merge($devices,$this->getByFormationIdWithProfiles($formationId));
        }

        return $devices;
    }
}
