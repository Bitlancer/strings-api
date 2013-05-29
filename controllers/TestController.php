<?php

namespace Application;

class TestController extends ResourcesController
{

    protected $uses = array('Device','Implementation');

    public function index(){
        $this->Device->setStatusByProviderId('active',array('a4e72731-970e-40a9-af9b-57ce7acfb3c1'));
    }
}
