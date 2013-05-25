<?php

namespace StringsLoadBalancer;

class LoadBalancer
{
	public function __construct($name,Protocol $protocol,$port,Algorithm $algorithm,$virtualIpType){

		$this->name = $name;
		$this->protocol = $protocol;
		$this->port = $port;
		$this->algorithm = $algorithm;
		$this->virtualIpType = $virtualIpType;
	}

	public function getName(){
		return $this->name;
	}

	public function getProtocol(){
		return $this->protocol;
	}

	public function getPort(){
		return $this->port;
	}

	public function getAlgorithm(){
		return $this->algorithm;
	}

	public function getVirtualIpType(){
		return $this->virtualIpType;
	}

	public function toArray(){
		return array(
			'name' => $this->getName(),
			'protocol' => $this->getProtocol()->__toString(),
			'port' => $this->getPort(),
			'algorithm' => $this->getAlgorithm()->__toString(),
			'virtualIpType' => $this->getVirtualIpType()
		);
	}

	public function __toString(){
		return json_encode($this);
	}
}
