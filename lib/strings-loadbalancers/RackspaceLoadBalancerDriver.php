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

	public function getLoadBalancers($filter=array()){

		$loadBalancers = array();

		$loadBalancerCollection = $this->connection->LoadBalancerList();
		while($loadBalancer = $loadBalancerCollection->Next()){

            $virtualIps = array();
            foreach($loadBalancer->virtualIps as $ip){
                $virtualIps[] = array(
                    'id' => $ip->id,
                    'type' => $ip->type,
                    'address' => $ip->address,
                    'version' => $ip->ipVersion
                );
            }

            $nodes = array();
            foreach($loadBalancer->nodes as $node){
                $nodes[] = array(
                    'id' => $node->id,
                    'ip' => $node->address,
                    'port' => $node->port
                );
            }

			$loadBalancers[] = array(
                'id' => $loadBalancer->id,
                'name' => $loadBalancer->name,
                'port' => $loadBalancer->port,
                'protocol' => $loadBalancer->protocol,
                'algorithm' => $loadBalancer->algorithm,
                'virtualIps' => $virtualIps,
                'nodes' => $nodes
            );
		}

		return $loadBalancers;
	}

	public function getAlgorithms(){

		$algorithms = array();

		$algoCollection = $this->connection->AlgorithmList();
		while($algorithm = $algoCollection->Next()){
			$algorithms[] = $algorithm->name;
		}

		return $algorithms;	
	}

	public function getProtocols(){

		$protocols = array();

		$protocolsCollection = $this->connection->ProtocolList();
		while($protocol = $protocolsCollection->Next()){
			$protocols[] = array(
                'name' => $protocol->name,
                'port' => $protocol->port
            );
		}

		return $protocols;
	}
			
	public function create($name,$protocol,$algorithm,$virtualIpType,$nodes,$wait=false,$waitTimeout=300){

		$loadBalancer = $this->connection->LoadBalancer();

        $virtualIpType = strtoupper($virtualIpType);
        if($virtualIpType == 'PRIVATE'){
            $virtualIpType = 'SERVICENET'; 
        }
		$loadBalancer->AddVirtualIp($virtualIpType);

        foreach($nodes as $node){
            $loadBalancer->AddNode($node['ip'],$node['port']);
        }
		
		$loadBalancer->Create(array(
			'name' => $name,
			'protocol' => $protocol['name'],
			'port' => $protocol['port'],
            'algorithm' => $algorithm
		));

        if($wait){
            $loadBalancer->WaitFor('ACTIVE',$waitTimeout);
            if($loadBalancer->Status() != 'ACTIVE')
                throw new OperationTimeoutException();
        }

        $nodes = array();
        foreach($loadBalancer->nodes as $node){
            $nodes[] = array(
                'id' => $node->id,
                'ip' => $node->address,
                'port' => $node->port
            );
        }

		return array(
			'id' => $loadBalancer->id,
			'name' => $loadBalancer->name,
            'nodes' => $nodes 
		);
	}

    public function delete($loadBalancerId){

		$loadBalancer = $this->connection->LoadBalancer($loadBalancerId);
		$loadBalancer->Delete();
	}

    public function getAlgorithm($loadBalancerId){

        $loadBalancer = $this->connection->LoadBalancer($loadBalancerId);
        return $loadBalancer->algorithm;
    }

    public function setAlgorithm($loadBalancerId,$algorithm,$wait=true,$waitTimeut=300){
        
        $loadBalancer = $this->connection->LoadBalancer($loadBalancerId);
        $loadBalancer->Update(array(
            'algorithm' => $algorithm
        ));

        if($wait){
            $loadBalancer->WaitFor('ACTIVE',$waitTimeout);
            if($loadBalancer->Status() != 'ACTIVE')
                throw new OperationTimeoutException();
        }
    }

    public function getProtocol($loadBalancerId){

        $loadBalancer = $this->connection->LoadBalancer($loadBalancerId);
        return array(
            'name' => $loadBalancer->protocol,
            'port' => $loadBalancer->port
        );
    }

    public function setProtocol($loadBalancerId,$protocol,$wait=true,$waitTimeout=300){

        $loadBalancer = $this->connection->LoadBalancer($loadBalancerId);
        $loadBalancer->Update(array(
            'protocol' => $protocol['name'],
            'port' => $protocol['port']
        ));

        if($wait){
            $loadBalancer->WaitFor('ACTIVE',$waitTimeout);
            if($loadBalancer->Status() != 'ACTIVE')
                throw new OperationTimeoutException();
        }
    }

    public function getNodes($loadBalancerId){

		$nodes = array();

		$loadBalancer = $this->connection->LoadBalancer($loadBalancerId);
        $nodeCollection = $loadBalancer->NodeList();
		while($node = $nodeCollection->Next()){
			$nodes[] = array(
				'id' => $node->id,
				'ip' => $node->address,
				'port' => $node->port,
				'condition' => $node->condition
			); 
		}

		return $nodes;
	}

    public function addNode($loadBalancerId,$node,$wait=false,$waitTimeout=300){
		
		$loadBalancer = $this->connection->LoadBalancer($loadBalancerId);

        print_r($loadBalancer->nodes);
        die();

	
		$loadBalancer->AddNode($node['ip'],$node['port']);
		$resp = $loadBalancer->AddNodes();

        if($wait){
            $loadBalancer->WaitFor('ACTIVE',$waitTimeout);
            if($loadBalancer->Status() != 'ACTIVE')
                throw new OperationTimeoutException();
        }

        $nodes = $this->getNodes($loadBalancerId);
        foreach($nodes as $n){
            if($n['ip'] == $node['ip']){
                return $n;
            }
        }
	}

    public function removeNode($loadBalancerId,$nodeId,$wait=false,$waitTimeout=300){

        $loadBalancer = $this->connection->LoadBalancer($loadBalancerId);
        $node = $loadBalancer->Node($nodeId);
        $node->Delete();

        if($wait){
            $loadBalancer->WaitFor('ACTIVE',$waitTimeout);
            if($loadBalancer->Status() != 'ACTIVE')
                throw new OperationTimeoutException();
        }
	}

    public function getStatus($loadBalancerId){

        $loadBalancer = $this->connection->LoadBalancer($loadBalancerId);
        return $this->toGenericStatus($loadBalancer->status);
    }

    protected function toGenericStatus($status){
        return strtolower($status);
    }
}
