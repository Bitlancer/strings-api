<?php

namespace StringsInfrastructure;

define('RAXSDK_CONNECTTIMEOUT', 10);

class RackspaceInfrastructureDriver extends OpenStackInfrastructureDriver 
{
    protected function parseConnectionParameters($connParams){
        
        $validConnectionDataStruct = array(
            'region' => '',
            'identityApiEndpoint' => '',
            'credentials' => array(
                'username' => '',
                'secret' => ''
            )
        );
        
        if(!self::validDataStructure($validConnectionDataStruct,$connParams))
            throw new \InvalidArgumentException('One or more required provider connection parameters is missing or is invalid.');
        
        $this->region = $connParams['region'];
        $this->identityAPIEndpoint = $connParams['identityApiEndpoint'];
        $this->connectionCredentials = array(
            'username' => $connParams['credentials']['username'],
            'apiKey' => $connParams['credentials']['secret']
        );
    }
    
    protected function getProviderConnection(){
    
        $connection = new \OpenCloud\Rackspace($this->identityAPIEndpoint,$this->connectionCredentials);
        return $connection->Compute('cloudServersOpenStack',$this->region);
    }
}
