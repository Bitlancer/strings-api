<?php

namespace StringsLoadBalancer;

abstract class LoadBalancerDriver
{
	public function __construct($connectionParameters){

		$this->parseConnectionParameters($connectionParameters);
		$this->connection = $this->getProviderConnection();
	}
	
	abstract protected function parseConnectionParameters($params);
	abstract protected function getProviderConnection();

	abstract public function getAlgorithms();
	abstract public function getProtocols();

	abstract public function getLoadBalancers($filter=array());

    abstract public function create($name,$protocol,$algorithm,$virtualIpType,$nodes,$wait=false,$waitTimeout=300);
	abstract public function delete($loadBalancerId);

    abstract public function getAlgorithm($loadBalancerId);
    abstract public function setAlgorithm($loadBalancerId,$algorithm);

    abstract public function getProtocol($loadBalancerId);
    abstract public function setProtocol($loadBalancerId,$protocol);

	abstract public function getNodes($loadBalancerId);
	abstract public function addNode($loadBalancerId,$node);
	abstract public function removeNode($loadBalancerId,$node);

    abstract public function getStatus($loadBalancerId);

	public static function validDataStructure($validDataStruc,$compDataStruc){

        if($diff = array_diff_key($validDataStruc,$compDataStruc))
            return false;

        foreach($validDataStruc as $index => $item){
            if(is_array($item) && !self::validDataStructure($item,$compDataStruc[$index]))
                return false;
        }

        return true;
    }
}
