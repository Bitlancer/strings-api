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

    abstract public function get($loadBalancerId);
    abstract public function create($attrs);
    abstract public function update($loadBalancerId, $attrs);
	abstract public function delete($loadBalancerId);

	abstract public function getNodes($loadBalancerId);
	abstract public function addNodes($loadBalancerId, $nodes);
	abstract public function removeNodes($loadBalancerId, $nodes);

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
