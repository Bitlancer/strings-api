<?php

namespace StringsDns;

abstract class DnsDriver
{
	public function __construct($connectionParameters){

		$this->parseConnectionParameters($connectionParameters);
		$this->connection = $this->getProviderConnection();
	}
	
	abstract protected function parseConnectionParameters($params);
	abstract protected function getProviderConnection();

    abstract public function addDomainRecord($domainId,$dnsRecord,$wait=false,$waitTimeout=300);

    abstract public function getDomainRecordByTypeAndName($domainId,$type,$name);

    abstract public function getDomainRecords($domainId,$filter=array());

    abstract public function removeDomainRecord($domainId,$recordId,$wait=false,$waitTimeout=300);

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
