<?php

namespace StringsLoadBalancer;

require_once('LoadBalancerDriver.php');
require(__DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'php-opencloud' . DIRECTORY_SEPARATOR . 'Autoload.php');

class RackspaceLoadBalancerDriver extends LoadBalancerDriver
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
		return $connection->LoadBalancerService('cloudLoadBalancers',$this->region);
	}

	public function getLoadBalancers(){

		$loadBalancers = array();

		$loadBalancerCollection = $this->connection->LoadBalancerList();
		while($loadBalancer = $loadBalancerCollection->Next()){
			$loadBalancers[] = RackspaceLoadBalancer::fromProviderObject($loadBalancer);
		}

		return $loadBalancers;
	}

	public function getAlgorithms(){

		$algorithms = array();

		$algoCollection = $this->connection->AlgorithmList();

		while($algorithm = $algoCollection->Next()){
			$algorithms[] = RackspaceLoadBalancerAlgorithm::fromProviderObject($algorithm);
		}

		return $algorithms;	
	}

	public function getProtocols(){

		$protocols = array();

		$protocolsCollection = $this->connection->ProtocolList();
		
		while($protocol = $protocolsCollection->Next()){
			$protocols[] = RackspaceLoadBalancerProtocol::fromProviderObject($protocol);
		}

		return $protocols;
	}
			
	public function create($name,$protocol,$port,$algorithm,$virtualIpType,$nodes,$providerSpecificParameters=array(),$wait=false,$waitTimeout=300){

		$loadBalancer = $this->connection->LoadBalancer();
		$loadBalancer->AddVirtualIp($virtualIpType);
		
		foreach($nodes as $node){
			list($nodeIp,$nodePort) = $node;
			$loadBalancer->AddNode($nodeIp,$nodePort);	
		}

		$loadBalancer->Create(array(
			'name' => $name,
			'protocol' => $protocol,
			'port' => $port,
			'algorithm' => $algorithm
		));

		if($wait)
			$loadBalancer->WaitFor('ACTIVE',$waitTimeout);

		return array(
			'id' => $loadBalancer->id,
			'name' => $loadBalancer->name
		);
	}

    public function delete($loadBalancerId){

		$loadBalancer = $this->connection->LoadBalancer($loadBalancerId);
		$loadBalancer->Delete();
	}

    public function getNodes($loadBalancerId){

		$nodes = array();

		$loadBalancer = $this->connection->LoadBalancer($loadBalancerId);
		while($node = $loadBalancer->Next()){
			$nodes[] = array(
				'id' => $node->Id(),
				'ipAddress' => $node->address,
				'port' => $node->port,
				'condition' => $node->condition
			); 
		}

		return $nodes;
	}

    public function addNode($loadBalancer,$node){
		
		$loadBalancer = $this->connection->LoadBalancer($loadBalancerId);
		
		list($nodeIp,$nodePort) = $node;
		$loadBalancer->AddNode($nodeIp,$nodePort);
		$loadBalancer->AddNodes();

	}

    public function removeNode($loadBalancer,$node){
		throw new \RuntimeException('Not implemented');
	}
}
