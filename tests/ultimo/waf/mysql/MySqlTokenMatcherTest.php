<?php

namespace ultimo\waf\mysql;

class MySqlTokenMatcherTest extends \PHPUnit_Framework_TestCase {
  protected $matcher;
  
  protected function setUp() {
    $this->matcher = new MySqlTokenMatcher();
  }
  
  public function testSomething() {

  }
}