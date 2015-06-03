<?php

namespace ultimo\waf\mysql;

// TODO: rename test functions, replace testMatcher...
class MySqlTokenMatcherTest extends \PHPUnit_Framework_TestCase {
  protected $matcher;
  protected $lexer;
  
  protected function setUp() {
    $this->matcher = new MySqlTokenMatcher();
    $this->lexer = new MySqlLexer();
  }
  
  public function testMatcherAcceptsAlternation() {
    $this->assertTrue($this->matcher->match(
      '/(%string%|%number%)/',
      $this->lexer->run('42')
    ));
  }
  
  public function testMatcherAcceptsAlternationWithPaths() {
    $this->assertTrue($this->matcher->match(
      '/(%string% %number%|%number% %string%)/',
      $this->lexer->run('42"string"')
    ));
  }
  
  public function testMatcherAcceptsPlusQuantifier() {
    $this->assertTrue($this->matcher->match(
      '/%string%+/',
      $this->lexer->run('"string""string"')
    ));
  }
  
  public function testMatcherAcceptsStarQuantifier() {
    $this->assertTrue($this->matcher->match(
      '/%string%* %whitespace% %number%/',
      $this->lexer->run(' 42')
    ));
  }
  
  public function testMatcherAcceptsQuantifier() {
    $this->assertTrue($this->matcher->match(
      '/%string%{2}/',
      $this->lexer->run('"string 1""string 2"')
    ));
  }
  
  public function testMatcherAcceptsCaretAnchor() {
    $this->assertTrue($this->matcher->match(
      '/^%number%/',
      $this->lexer->run('42')
    ));
  }
  
  public function testMatcherAcceptsDollarAnchor() {
    $this->assertTrue($this->matcher->match(
      '/%number%$/',
      $this->lexer->run('42')
    ));
  }
  
  public function testMatcherMatchesExecutedCommentContent() {
    $this->assertTrue($this->matcher->match(
      '/%identifier% %executed-comment-start% %identifier%/',
      $this->lexer->run('a/*!b*/42')
    ));
  }
  
  public function testMatcherMatchesConditionalCommentContent() {
    $this->assertTrue($this->matcher->match(
      '/%identifier% %conditional-comment-start% %identifier%/',
      $this->lexer->run('a/*!0b*/42')
    ));
  }
  
  public function testMatcherMatchesConditionalCommentContentInExplodeMode() {
    $this->assertTrue($this->matcher->match(
      '/%identifier% %conditional-comment-start% %identifier% %comment-end%/',
      $this->lexer->run('a/*!0b*/42'),
      true
    ));
  }
  
  public function testMatcherIgnoresConditionalCommentContentInExplodeMode() {
    $this->assertTrue($this->matcher->match(
      '/%identifier% %conditional-comment-start% %comment-end% %number%/',
      $this->lexer->run('a/*!0b*/42'),
      true
    ));
  }
  
  public function testMatcherMatchesUnterminatedConditionalCommentContentInExplodeMode() {
    $this->assertTrue($this->matcher->match(
      '/^%identifier% %conditional-comment-start% %identifier%$/',
      $this->lexer->run('a/*!0b'),
      true
    ));
  }
  
  public function testMatcherIgnoresUnterminatedConditionalCommentContentInExplodeMode() {
    $this->assertTrue($this->matcher->match(
      '/^%identifier% %conditional-comment-start%$/',
      $this->lexer->run('a/*!0b'),
      true
    ));
  }
  
  public function testMatcherMatchesNestedConditionalCommentAsAWhole() {
    // nested comments are ended with one ending comment
    $this->assertFalse($this->matcher->match(
      '/%identifier% %whitespace%* %number% %whitespace%* %identifier%/',
      $this->lexer->run('a /*!0 2 /*!1 "s" */ c'),
      true
    ));
  }
  
  public function testMatcherUnmatchesTokenTypeNotInPattern() {
    $this->assertFalse($this->matcher->match(
      '/%string%/',
      $this->lexer->run('42')
    ));
  }
  
  public function testMatcherAcceptsExpressionTokens() {
    $this->assertTrue($this->matcher->match(
      '/%identifier% (%[^\%]+?%)* %identifier%/',
      $this->lexer->run('a 1 c')
    ));
  }
}