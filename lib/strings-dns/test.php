#!/usr/bin/php
<?php

require 'Autoload.php';

$providerConnection = array(
	'region' => 'DFW',
    'identityApiEndpoint' => 'https://identity.api.rackspacecloud.com/v2.0/',
    'credentials' => array(
        'username' => '',
        'secret' => ''
    )
);

$rackspace = new \StringsDns\RackspaceDnsDriver($providerConnection);

$domain = new \StringsDns\RackspaceDnsDomain(null,'semspider.com',300,array('email' => 'jcotton@semspider.com'));
$subdomain = new \StringsDns\DnsDomain(null,'test.semspider.com','300',array('email' => 'jcotton@semspider.com'));

$domain = $rackspace->createDomain($domain,true);
print_r($domain);

$domainId = $domain->getId();

$subdomain = $rackspace->createSubdomain($domainId,$subdomain,true);
print_r($subdomain);

$record = new \StringsDns\DnsRecord(null,'A','www.semspider.com','108.166.90.117',300,false);
$record = $rackspace->addDomainRecord($domainId,$record,true);
print_r($record);

