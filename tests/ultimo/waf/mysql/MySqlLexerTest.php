<?php

namespace ultimo\waf\mysql;

class MySqlLexerTest extends \PHPUnit_Framework_TestCase {
  protected $lexer;
  
  protected function setUp() {
    $this->lexer = new MySqlLexer();
  }
  
  public function providerAlphaCharacter() {
    return array(
      array('a'),
      array('_')
    );
  }
  
  public function providerWhitespace() {
    return array(
      array("\n"),
      array("\t"),
      array("\r"),
      array(urldecode("%0B")),
      array(urldecode("%0C"))
    );
  }
  
  public function providerHorizontalWhitespace() {
    return array(
      array("\t"),
      array("\r"),
      array(urldecode("%0B")),
      array(urldecode("%0C"))
    );
  }
  
  public function testIntIsParsedAsNumber() {
    $this->assertContains(
      array('type' => 'number', 'value' => '42'),
      $this->lexer->run('42')
    );
  }
  
  public function testFloatIsParsedAsNumber() {
    $this->assertContains(
      array('type' => 'number', 'value' => '42.1337'),
      $this->lexer->run('42.1337')
    );
  }
  
  /**
   * @dataProvider providerWhitespace
   */
  function testNumberFollowedByWhitespaceIsParsedAsNumber($whitespace) {
    echo urlencode($whitespace);
    $this->assertContains(
      array('type' => 'number', 'value' => '42'),
      $this->lexer->run('42'.$whitespace)
    );
  }
  
  /**
   * @dataProvider providerAlphaCharacter
   */
  public function testNumberFollowedByAlphaCharaterIsNotParsedAsNumber($alphaChar) {
    $this->assertNotContains(
      array('type' => 'number', 'value' => '42'),
      $this->lexer->run('42' . $alphaChar)
    );
  }
  
  public function testIntFollowedByDotCharaterIsNotParsedAsNumber() {
    $this->assertNotContains(
      array('type' => 'number', 'value' => '42'),
      $this->lexer->run('42.')
    );
  }
  
  public function testFloatFollowedByDotCharaterIsNotParsedAsNumber() {
    $this->assertNotContains(
      array('type' => 'number', 'value' => '42.1337.'),
      $this->lexer->run('42.1337.')
    );
  }
  
  public function testNumberFollowedByCommaIsParsedAsNumber() {
    $this->assertContains(
      array('type' => 'number', 'value' => '42'),
      $this->lexer->run('42,')
    );
  }
}