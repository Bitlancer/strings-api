<?php

namespace StringsInfrastructure;

class AmazonInfrastructureDriver extends OpenStackInfrastructureDriver 
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

        $ec2Client = \Aws\Ec2\Ec2Client::factory(array(
            'key'    => 'YOUR_AWS_ACCESS_KEY_ID',
            'secret' => 'YOUR_AWS_SECRET_ACCESS_KEY',
            'region' => $this->region,
        ));

        return $ec2Client;
	}
}
