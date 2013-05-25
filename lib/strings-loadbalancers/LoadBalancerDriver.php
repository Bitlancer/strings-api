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

	abstract public function create(LoadBalancer $loadBalancer,$wait=false,$waitTimeout=300);
	abstract public function delete($loadBalancerId);

	abstract public function getNodes($loadBalancerId);
	abstract public function addNode($loadBalancerId,$node);
	abstract public function removeNode($loadBalancerId,$node);

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
