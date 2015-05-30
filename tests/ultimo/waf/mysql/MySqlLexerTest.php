<?php

namespace ultimo\waf\mysql;

class MySqlLexerTest extends \PHPUnit_Framework_TestCase {
  protected $lexer;
  
  protected function setUp() {
    $this->lexer = new MySqlLexer();
  }
  
  public function testIntIsParsedAsNumber() {
    $actual = $this->lexer->run('42');
    $this->assertEquals(array(
        array('type' => 'number', 'value' => '42')
    ), $actual);
  }
}