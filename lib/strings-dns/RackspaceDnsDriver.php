<?php

namespace StringsDns;

require_once('DnsDriver.php');
require(__DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'php-opencloud' . DIRECTORY_SEPARATOR . 'Autoload.php');

class RackspaceDnsDriver extends DnsDriver
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
		return $connection->DNS('cloudDNS',$this->region);
	}

	public function getDomain($domainId){

        $domain = $this->connection->Domain($domainId);
		return RackspaceDnsDomain::fromProviderObject($domain);
    }

    public function getDomainByName($name){

        $domainCollection = $this->getDomains(array('name' => $name));
        $domain = array_pop($domainCollection);
        return $domain;
    }
	
	public function getDomains($filter=array()){

		$domains = array();
	
		$domainCollection = $this->connection->DomainList($filter);
		while($domain = $domainCollection->Next()){
			$domains[] = RackspaceDnsDomain::fromProviderObject($domain);
		}
		
		return $domains;
	}

	public function getSubdomains($domainId,$filter=array()){

		$domain = $this->connection->Domain($domainId);
	
		$subdomains = array();	
		$subdomainCollection = $domain->SubdomainList($filter);

		while($subdomain = $subdomainCollection->Next()){
			$subdomains[] = RackspaceDnsDomain::fromProviderObject($subdomain);
		}
		
		return $subdomains;
	}

    public function createDomain(DnsDomain $dnsDomain,$wait=false,$waitTimeout=300){

		$domain = $this->connection->Domain();
		$job = $domain->Create(array(
			'name' => $dnsDomain->getDomain(),
			'ttl' => $dnsDomain->getTtl(),
			'emailAddress' => $dnsDomain->getContactInformation('email')
		));

		if($wait)
			$job->WaitFor('COMPLETED',$waitTimeout);

		return $this->getDomainByName($dnsDomain->getDomain());
	}

    public function createSubdomain($domainId,DnsDomain $subdomain,$wait=false,$waitTimeout=300){
		return $this->createDomain($subdomain,$wait,$waitTimeout);
	}

	public function getDomainRecords($domainId,$filter=array()){

		$domain = $this->connection->Domain($domainId);

		$records = array();
		$recordCollection = $domain->RecordList($filter);
		while($record = $recordCollection->Next()){
			$records[] = RackspaceDnsRecord::fromProviderObject($record);
		}

		return $records;
	}

	public function getDomainRecordByTypeAndName($domainId,$type,$name){
		return array_pop($this->getDomainRecords($domainId,array('type' => $type,'name' => $name)));
	}

	public function getDomainRecord($domainId,$recordId){

		$domain = $this->connection->Domain($domainId);
		$record = $domain->Record($recordId);
		return RackspaceDnsRecord::fromProviderObject($record);
	}

    public function addDomainRecord($domainId,DnsRecord $dnsRecord,$wait=false,$waitTimeout=300){

		$domain = $this->connection->Domain($domainId);
		$record = $domain->Record();

		$recordData = array(
			'type' => $dnsRecord->getType(),
			'name' => $dnsRecord->getName(),
			'ttl' => $dnsRecord->getTtl(),
			'data' => $dnsRecord->getData()
		);

		if($dnsRecord->getPriority() !== false)
			$recordData['priority'] = $dnsRecord->getPriority();

		$job = $record->Create($recordData);

		if($wait)
			$job->WaitFor('COMPLETED',$waitTimeout);

		return $this->getDomainRecordByTypeAndName($domainId,$dnsRecord->getType(),$dnsRecord->getName());
	}

    public function removeDomainRecord($domainId,$recordId,$wait=false,$waitTimeout=300){

        $domain = $this->connection->Domain($domainId);
        $record = $domain->Record($recordId);

        $job = $record->Delete();

        if($wait)
            $job->WaitFor('COMPLETED',$waitTimeout);
    }

	/*
    public function addPtrRecord(DnsRecord $dnsRecord,$wait=false,$waitTimeout=300){

		$ptrRecord = $this->connection->PtrRecord();

		$recordData = array(
            'type' => $dnsRecord->getType(),
            'name' => $dnsRecord->getName(),
            'ttl' => $dnsRecord->getTtl(),
            'data' => $dnsRecord->getData()
        );

        if($dnsRecord->getPriority() !== false)
            $recordData['priority'] = $dnsRecord->getPriority();

        $job = $ptrRecord->Create($recordData);

        if($wait)
            $job->WaitFor('COMPLETED',$waitTimeout);

        //return $this->getDomainRecordByTypeAndName($domainId,$dnsRecord->getType(),$dnsRecord->getName());
	}
	*/
}
