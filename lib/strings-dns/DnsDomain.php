<?php

namespace StringsDns;

class DnsDomain
{
	public function __construct($id=null,$domain,$ttl,$contactInformation=array()){
		$this->id = $id;
		$this->domain = $domain;
		$this->ttl = $ttl;
		$this->contactInformation = $contactInformation;
	}

	public function getId(){
		return $this->id;
	}

	public function getDomain(){
		return $this->domain;
	}

	public function getTtl(){
		return $this->ttl;
	}

	public function getContactInformation($key=false){
		if($key === false)
			return $this->contactInformation;
		else
			return $this->contactInformation[$key];
	}

	public function toArray(){
		return array(
			'id' => $this->getId(),
			'domain' => $this->getDomain(),
			'ttl' => $this->getTtl(),
			'contactInformation' => $this->contactInformation()
		);
	}

	public function __toString(){
		return json_encode($this);
	}
}
