<?php
/**
 * Currently this class is just a proof of concept. Many performance
 * optimizations can be applied.
 */
namespace ultimo\waf\mysql;

class MySqlTester {

  public $debug = false;
  
  protected $lexer;
  protected $aliases = array();
  protected $rules;
  
  public function __construct(array $config) {
    $customTokenTypes = array();
    if (isset($config['custom-token-types'])) {
      $customTokenTypes = $config['custom-token-types'];
    }
    $this->lexer = new MySqlLexer($customTokenTypes);
    
    if (isset($config['aliases'])) {
      $this->setAliases($config['aliases']);
    }
    
    if (!isset($config['rules'])) {
      throw new \Exception("No rules in config");
    }
    $this->setRules($config['rules']);
  }
  
  protected function setAliases(array $aliases) {
    $this->aliases = array();
    foreach ($aliases as $name => $def) {
      $this->aliases[$name] = str_replace(array_keys($this->aliases), array_values($this->aliases), $def);
    }
  }
  
  protected function setRules(array $rules) {
    $this->rules = array();
    $aliasesKeys = array_keys($this->aliases);
    $aliasesDefs = array_values($this->aliases);
    foreach ($rules as $rule) {
      $rule['compiled_pattern'] = str_replace($aliasesKeys, $aliasesDefs, $rule['pattern']);
      $this->rules[] = $rule;
    }
  }
  
  // TODO: optimize for performace by precompiling the subject and pattern
  public function test($value) {
    $bestResult = array('score' => 0, 'rules' => array());
    
    $matcher = new MySqlTokenMatcher();
    
    foreach (array('') as $delimiter) { // , '"', "'"
      $result = array('score' => 0, 'rules' => array());
      
      $tokens = $this->lexer->run($delimiter . $value . $delimiter);
      
      foreach ($this->rules as $rule) {
        $isMatch = $matcher->match($rule['compiled_pattern'], $tokens);
        
        $negate = isset($rule['negate']) ? $rule['negate'] : false;
        
        //echo $rule['compiled_pattern'] . ' negate: ' . (($negate) ? 'true' : 'false') . ', match: ' . (($isMatch) ? 'true' : 'false') . "\n";
        
        if ($isMatch != $negate) {
          unset($rule['compiled_pattern']);
          $result['rules'][] = $rule;
          $result['score'] += $rule['score'];
        }
      }
      
      if ($result['score'] > $bestResult['score']) {
        $bestResult = $result;
      }
    }

    return $bestResult;
  }
  
  protected function write($str) {
    if ($this->debug) {
      echo $str;
    }
  }
}