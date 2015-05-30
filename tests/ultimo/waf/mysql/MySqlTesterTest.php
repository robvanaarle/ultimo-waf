<?php

namespace ultimo\waf\mysql;

class MySqlTesterTest extends \PHPUnit_Framework_TestCase {
  protected $tester;
  
  protected function setUp() {
    $this->tester = new MySqlTester();
  }
  
  public function testTextbookExampleIsPositive() {
    $this->assertTrue($this->tester->test("1' or 1=1--"));
  }
  
  public function testMyNameIsNegative() {
    $this->assertFalse($this->tester->test('Rob'));
  }
}