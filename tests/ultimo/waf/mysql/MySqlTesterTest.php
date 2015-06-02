<?php

namespace ultimo\waf\mysql;

class MySqlTesterTest extends \PHPUnit_Framework_TestCase {
  public function testBasicTokenTypeIsMatched() {
    $config = array(
      'rules' => array(
        array(
          'message' => 'unknown token present',
          'pattern' => '/%unknown%/',
          'score' => 1
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test(':foo')
    );
  }
  
  public function testCustomTokenTypeIsMatched() {
    $config = array(
      'custom-token-types' => array(
        'keyword' => array ('select', 'from'),
      ),
      'rules' => array(
        array(
          'message' => 'keyword present',
          'pattern' => '/%keyword%/',
          'score' => 1
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test('select')
    );
  }
  
  public function testAliasIsMatched() {
    $config = array(
      'aliases' => array(
        '%operand%' => '%number%|%string%'
      ),
      'rules' => array(
        array(
          'message' => 'containing operand',
          'pattern' => '/%operand%/',
          'score' => 1
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test('42')
    );
  }
  
  public function testAliasCanReferenceCustomType() {
    $config = array(
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
          'score' => 1
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test('select')
    );
  }
  
  public function testAliasCanReferenceEarlierDefinedAlias() {
    $config = array(
      'aliases' => array(
        '%operand%' => '%number%',
        '%operand-or-string%' => '%operand%|%string%'
      ),
      'rules' => array(
        array(
          'message' => 'containing one operand or string',
          'pattern' => '/^%operand-or-string%$/',
          'score' => 1
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test('42')
    );
  }
  
  public function testAliasCanContainNonTokenExperssions() {
    $config = array(
      'aliases' => array(
        '%any%' => '%[^\%]+?%'
      ),
      'rules' => array(
        array(
          'message' => 'two numbers between one token',
          'pattern' => '/%number% %any% %number%/',
          'score' => 1
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test('42"hello"42')
    );
  }
  
  public function testNegateRuleIsActiveWhenNotMatched() {
    $config = array(
      'rules' => array(
        array(
          'message' => 'not containing a number',
          'pattern' => '/%number%/',
          'score' => 1,
          'negate' => true
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test('"xxx"')
    );
  }
  
  public function testMultipleRulesMatched() {
    $config = array(
      'rules' => array(
        array(
          'message' => 'unknown token present',
          'pattern' => '/%unknown%/',
          'score' => 1
        ),
        array(
          'message' => 'no string present',
          'pattern' => '/%string%/',
          'score' => 1,
          'negate' => true
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertEquals(
      array(
        'score' => 2,
        'rules' => array($config['rules'][0], $config['rules'][1])
      ), 
      $tester->test(':foo')
    );
  }
  
  public function testRuleMatchingCanBeStoppedWhenMatched() {
    $config = array(
      'rules' => array(
        array(
          'message' => 'unknown token present',
          'pattern' => '/%unknown%/',
          'score' => 1,
          'stop' => true
        ),
        array(
          'message' => 'no string present',
          'pattern' => '/%string%/',
          'score' => 1,
          'negate' => true
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test(':foo')
    );
  }
  
  public function testConditionalCommentContentIsMatchedInExplodeMatchAny() {
    $config = array(
      'aliases' => array(
        '%_%' => '%comment%|%executed-comment-start%|%conditional-comment-start%|%comment-end%|%whitespace%'
      ),
      'rules' => array(
        array(
          'message' => 'string followed by number',
          'pattern' => '/%string% %_%+ %number%/',
          'score' => 1,
          'explode' => 'match-any'
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test('"foo" /*!0 42 */')
    );
  }
  
  public function testConditionalCommentContentIsIgnoredInExplodeMatchAny() {
    $config = array(
      'aliases' => array(
        '%_%' => '%comment%|%executed-comment-start%|%conditional-comment-start%|%comment-end%|%whitespace%'
      ),
      'rules' => array(
        array(
          'message' => 'string followed by number',
          'pattern' => '/%string% %_%+ %number%/',
          'score' => 1,
          'explode' => 'match-any'
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test('"foo" /*!0 id */ 42')
    );
  }
  
  public function testConditionalCommentContentIsNotIgnoredInNonExplodeMode() {
    $config = array(
      'aliases' => array(
        '%_%' => '%comment%|%executed-comment-start%|%conditional-comment-start%|%comment-end%|%whitespace%'
      ),
      'rules' => array(
        array(
          'message' => 'string followed by number',
          'pattern' => '/%string% %_%+ %number%/',
          'score' => 1,
          'explode' => 'no'
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertNotEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test('"foo" /*!0 id */ 42')
    );
  }
  
  public function testPatternInConditionalCommentIsNotMatchedInExplodeMatchAll() {
    $config = array(
      'aliases' => array(
        '%_%' => '%comment%|%executed-comment-start%|%conditional-comment-start%|%comment-end%|%whitespace%'
      ),
      'rules' => array(
        array(
          'message' => 'string followed by number',
          'pattern' => '/%string% %_%+ %number%/',
          'score' => 1,
          'explode' => 'match-all'
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertNotEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test('"foo" /*!0 id */ 42')
    );
  }
  
  public function testStringWithNoEndSingleQuoteIsMatchedInQuotedMatchAny() {
    $config = array(
      'rules' => array(
        array(
          'message' => 'starts with string',
          'pattern' => '/^%string%/',
          'score' => 1,
          'quoted' => 'match-any'
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test('1\' or 1=1--')
    );
  }
  
  public function testStringWithNoEndDoubleQuoteIsMatchedInQuotedMatchAny() {
    $config = array(
      'rules' => array(
        array(
          'message' => 'starts with string',
          'pattern' => '/^%string%/',
          'score' => 1,
          'quoted' => 'match-any'
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test('1" or 1=1--')
    );
  }
  
  public function testPatternIsNotMatchedInQuotedMatchAll() {
    $config = array(
      'rules' => array(
        array(
          'message' => 'starts with string',
          'pattern' => '/^%string%/',
          'score' => 1,
          'quoted' => 'match-all'
        )
      )
    );
    
    $tester = new MySqlTester($config);
    
    $this->assertNotEquals(
      array(
        'score' => 1,
        'rules' => array($config['rules'][0])
      ), 
      $tester->test('1"')
    );
  }
  
}