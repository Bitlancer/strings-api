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

	abstract public function getDomain($domainId);
	abstract public function getDomainByName($name);
	
	abstract public function getDomains($filter=array());
	abstract public function getSubdomains($domainId,$filter=array());
	
	abstract public function createDomain(DnsDomain $dnsDomain,$wait=false,$waitTimeout=300);
	abstract public function createSubdomain($domainId,DnsDomain $dnsDomain,$wait=false,$waitTimeout=300);
	
	abstract public function getDomainRecords($domainId,$filter=array());
	abstract public function getDomainRecord($domainId,$recordId);
	
	abstract public function addDomainRecord($domainId,DnsRecord $dnsRecord,$wait=false,$waitTimeout=300);
    abstract public function removeDomainRecord($domainId,$recordId,$wait=false,$waitTimeout=300);
	
	//abstract public function addPtrRecord(DnsRecord $dnsRecord,$wait=false,$waitTimeout=300);

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
