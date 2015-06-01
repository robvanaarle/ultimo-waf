<?php

namespace ultimo\waf\mysql;

class MySqlTesterTest extends \PHPUnit_Framework_TestCase {
  public function testBasicTokenTypeIsMatched() {
    $tester = new MySqlTester(array(
      'rules' => array(
        array(
          'message' => 'unknown token present',
          'pattern' => '/%unknown%/',
          'score' => 1,
          'negate' => false
        )
      )
    ));
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array(
          array(
            'message' => 'unknown token present',
            'pattern' => '/%unknown%/',
            'score' => 1,
            'negate' => false
          )
        )
      ), 
      $tester->test(':foo')
    );
  }
  
  public function testCustomTokenTypeIsMatched() {
    $tester = new MySqlTester(array(
      'custom-token-types' => array(
        'keyword' => array ('select', 'from'),
      ),
      'rules' => array(
        array(
          'message' => 'keyword present',
          'pattern' => '/%keyword%/',
          'score' => 1,
          'negate' => false
        )
      )
    ));
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array(
          array(
            'message' => 'keyword present',
            'pattern' => '/%keyword%/',
            'score' => 1,
            'negate' => false
          )
        )
      ), 
      $tester->test('select')
    );
  }
  
  public function testAliasIsMatched() {
    $tester = new MySqlTester(array(
      'aliases' => array(
        '%operand%' => '%number%|%string%'
      ),
      'rules' => array(
        array(
          'message' => 'containing operand',
          'pattern' => '/%operand%/',
          'score' => 1,
          'negate' => false
        )
      )
    ));
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array(
          array(
            'message' => 'containing operand',
            'pattern' => '/%operand%/',
            'score' => 1,
            'negate' => false
          )
        )
      ), 
      $tester->test('42')
    );
  }
  
  public function testAliasCanReferenceCustomType() {
    $tester = new MySqlTester(array(
      'custom-token-types' => array(
        'keyword' => array ('select', 'from'),
      ),
      'aliases' => array(
        '%operand%' => '%number%|%string%|%keyword%'
      ),
      'rules' => array(
        array(
          'message' => 'containing operand',
          'pattern' => '/%operand%/',
          'score' => 1,
          'negate' => false
        )
      )
    ));
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array(
          array(
            'message' => 'containing operand',
            'pattern' => '/%operand%/',
            'score' => 1,
            'negate' => false
          )
        )
      ), 
      $tester->test('select')
    );
  }
  
  public function testAliasCanReferenceEarlierDefinedAlias() {
    $tester = new MySqlTester(array(
      'aliases' => array(
        '%operand%' => '%number%|%string%',
        '%operand-or-string%' => '%operand%|%string%'
      ),
      'rules' => array(
        array(
          'message' => 'containing operand or string',
          'pattern' => '/%operand-or-string%/',
          'score' => 1,
          'negate' => false
        )
      )
    ));
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array(
          array(
            'message' => 'containing operand or string',
            'pattern' => '/%operand-or-string%/',
            'score' => 1,
            'negate' => false
          )
        )
      ), 
      $tester->test('42')
    );
  }
  
  public function testNegateRuleIsActiveWhenNotMatched() {
    $tester = new MySqlTester(array(
      'rules' => array(
        array(
          'message' => 'not containing a number',
          'pattern' => '/%number%/',
          'score' => 1,
          'negate' => true
        )
      )
    ));
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array(
          array(
            'message' => 'not containing a number',
            'pattern' => '/%number%/',
            'score' => 1,
            'negate' => true
          )
        )
      ), 
      $tester->test('"xxx"')
    );
  }
  
}