<?php

namespace Application;

class TestController extends ResourcesController
{

    protected $uses = array('Device','Implementation');

    public function index(){

    }
}
