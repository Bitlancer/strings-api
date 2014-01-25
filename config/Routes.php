<?php

namespace APIFramework;

/**
* Routes
*/

Router::registerRoute('GET','/Test/index','Test','index');

//Formations
Router::registerRoute(array('GET','POST'),'/Formations/create/:formationId','Formations','create');
Router::registerRoute(array('GET','POST'),'/Formations/delete/:formationId','Formations','delete');

//Instances
Router::registerRoute(array('GET','POST'),'/Instances/create/:deviceId','Instances','create');
Router::registerRoute(array('GET','POST'),'/Instances/delete/:deviceId','Instances','delete');
Router::registerRoute(array('GET','POST'),'/Instances/resize/:deviceId/:flavorId','Instances','resize');
Router::registerRoute(array('GET','POST'),'/Instances/confirmResize/:deviceId','Instances','confirmResize');
Router::registerRoute(array('GET','POST'),'/Instances/revertResize/:deviceId','Instances','revertResize');
Router::registerRoute(array('GET','POST'),'/Instances/reboot/:deviceId','Instances','reboot');
Router::registerRoute(array('GET','POST'),'/Instances/getStatus/:deviceId','Instances','getStatus');
Router::registerRoute(array('GET','POST'),'/Instances/updateServerStatuses/:implementationId/:region/','Instances','updateServerStatuses');

//Load balancers
Router::registerRoute(array('GET','POST'),'/LoadBalancers/create/:deviceId','LoadBalancers','create');
Router::registerRoute(array('GET','POST'),'/LoadBalancers/delete/:deviceId','LoadBalancers','delete');
Router::registerRoute(array('GET','POST'),'/LoadBalancers/manageNodes/:deviceId','LoadBalancers','manageNodes');

//DNS
Router::registerRoute(array('GET','POST'),'/Dns/addDeviceARecord/:deviceId','Dns','addDeviceARecord');

//Remote execution
Router::registerRoute(array('GET','POST'),'/RemoteExecution/run/:scriptId','RemoteExecution','run');
