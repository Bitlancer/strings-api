<?php

namespace StringsInfrastructure;

abstract class InfrastructureDriver
{
	public function __construct($connectionParameters){

		$this->parseConnectionParameters($connectionParameters);
		$this->connection = $this->getProviderConnection();
	}
	
	abstract protected function parseConnectionParameters($params);
	abstract protected function getProviderConnection();
	
	abstract public function createServer($name,$flavor,$image,$wait=false,$waitTimeout=600);
	abstract public function resizeServer($serverID,$flavor,$wait=false,$waitTimeout=600);
    abstract public function confirmResizeServer($serverID);
    abstract public function revertResizeServer($serverID);

    abstract public function rebuildServer($serverID,$flavor,$image,$wait=false,$waitTimeout=600);
    abstract public function deleteServer($serverID);
    abstract public function rebootServer($serverID);

	abstract public function getServerStatus($serverID);

    abstract public function getServerFlavor($serverID);

    abstract public function getServerPublicIPv4Address($serverID);
    abstract public function getServerPrivateIPv4Address($serverID);

    abstract public function getServerPublicIPv6Address($serverID);
    abstract public function getServerPrivateIPv6Address($serverID);

    abstract public function getServers($filter=array());

	abstract public function getImages();
	abstract public function getFlavors(); 

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
