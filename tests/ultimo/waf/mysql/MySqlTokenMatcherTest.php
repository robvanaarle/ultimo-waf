<?php

namespace ultimo\waf\mysql;

class MySqlTokenMatcherTest extends \PHPUnit_Framework_TestCase {
  protected $matcher;
  protected $lexer;
  
  protected function setUp() {
    $this->matcher = new MySqlTokenMatcher();
    $this->lexer = new MySqlLexer();
  }
  
  public function testPatternDepth1IsMatched() {
    $this->assertEquals(array(
        array('type' => 'number', 'value' => '42')
      ),
      $this->matcher->match(array(
        'number' => null,
        'string' => array(
          'number' => null
        )
      ), $this->lexer->run('42'))
    );
  }
  
  public function testPatternDepth2IsMatched() {
    $this->assertEquals(array(
        array('type' => 'number', 'value' => '42'),
        array('type' => 'identifier', 'value' => 'xx')
      ),
      $this->matcher->match(array(
        'number' => array(
          'number' => null,
          'identifier' => null
        )
      ), $this->lexer->run('42 xx'))
    );
  }
  
  public function testCommentIsIgnored() {
    $this->assertEquals(array(
        array('type' => 'number', 'value' => '42'),
        array('type' => 'identifier', 'value' => 'xx')
      ),
      $this->matcher->match(array(
        'number' => array(
          'identifier' => null
        )
      ), $this->lexer->run('42 /* foobar */ xx'))
    );
  }
  
  public function testExecutedCommentIsEvaluated() {
    $this->assertEquals(array(
        array('type' => 'number', 'value' => '42'),
        array('type' => 'identifier', 'value' => 'foobar')
      ),
      $this->matcher->match(array(
        'number' => array(
          'identifier' => null
        )
      ), $this->lexer->run('42 /*! foobar */ xx'))
    );
  }
  
  public function testUnexpectedCommentEndIsIgnored() {
    $this->assertEquals(array(
        array('type' => 'number', 'value' => '42'),
        array('type' => 'identifier', 'value' => 'xx')
      ),
      $this->matcher->match(array(
        'number' => array(
          'identifier' => null
        )
      ), $this->lexer->run('42 */ xx'))
    );
  }
  
  public function testConditionalCommentIsBothEvaluatedAndIgnored() {
    $value = '42 /*!000 "xx" */ yy';
    
    
    $this->assertEquals(array(
        array('type' => 'number', 'value' => '42'),
        array('type' => 'string', 'value' => '"xx"')
      ),
          
      $this->matcher->match(array(
        'number' => array(
          'string' => null
        )
      ), $this->lexer->run($value))
    );
    
    $this->assertEquals(array(
        array('type' => 'number', 'value' => '42'),
        array('type' => 'identifier', 'value' => 'yy')
      ),
          
      $this->matcher->match(array(
        'number' => array(
          'identifier' => null
        )
      ), $this->lexer->run($value))
    );
  }
  
  public function testNestedConditionalCommentIsBothEvaluatedAndIgnored() {
    $value = '42 /*!000 "xx" /*!001 zz  */ 43 */ yy';
    
    
    $this->assertEquals(array(
        array('type' => 'number', 'value' => '42'),
        array('type' => 'string', 'value' => '"xx"'),
        array('type' => 'identifier', 'value' => 'zz')
      ),
          
      $this->matcher->match(array(
        'number' => array(
          'string' => array(
            'identifier' => null
          )
        )
      ), $this->lexer->run($value))
    );
    
    $this->assertEquals(array(
        array('type' => 'number', 'value' => '42'),
        array('type' => 'string', 'value' => '"xx"'),
        array('type' => 'number', 'value' => '43')
      ),
          
      $this->matcher->match(array(
        'number' => array(
          'string' => array(
            'number' => null
          )
        )
      ), $this->lexer->run($value))
    );
  }
  
  public function testPatternDepth1HasNoMatch() {
    $this->assertEquals(null,
      $this->matcher->match(array(
        'string' => null
      ), $this->lexer->run('42'))
    );
  }
  
  public function testPatternDepth2HasNoMatch() {
    $this->assertEquals(null,
      $this->matcher->match(array(
        'number' => array(
          'string' => null
        )
      ), $this->lexer->run('42 xx'))
    );
  }
}