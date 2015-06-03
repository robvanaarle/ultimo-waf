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
    
    foreach ($this->aliases as $name => $def) {
      $this->aliases[$name] = '(' . $def . ')';
    }
  }
  
  protected function setRules(array $rules) {
    $this->rules = array();
    $aliasesKeys = array_keys($this->aliases);
    $aliasesDefs = array_values($this->aliases);
    foreach ($rules as $rule) {
      $rule['expanded_pattern'] = str_replace($aliasesKeys, $aliasesDefs, $rule['pattern']);
      $this->rules[] = $rule;
    }
  }
  
  // TODO: optimize for performace by precompiling the subject and pattern
  // TODO: accept multiple variablenames with values, assign scores to variables
  public function test($value) {
    $matcher = new MySqlTokenMatcher();
  
    $result = array('score' => 0, 'rules' => array());
    
    foreach ($this->rules as $rule) {
      $quoted = isset($rule['quoted']) ? $rule['quoted'] : "no";
      
      $delimiters = array('');
      if ($quoted != "no") {
        $delimiters = array('', '"', "'");
      }
      
      
      $explode = isset($rule['explode']) ? $rule['explode'] : "no";
      $negate = isset($rule['negate']) ? $rule['negate'] : false;
      
      $quotedAllMatched = true;
      $quotedAnyMatched = false;
      
      foreach ($delimiters as $delimiter) {
        // TODO: optimize, encapsulating in delimiters is of no use if the value does not contiain that delimier
        $tokens = $this->lexer->run($delimiter . $value . $delimiter);
        
        // as string delimiters are placed, it's possible that the value becomes one string, which is of no interest to the rule
        if ($delimiter != '' && count($tokens) == 1 && $tokens[0]['type'] == 'string') {
          continue;
        }
        
        $subjects = $matcher->compileSubject($tokens, $explode != "no");
        $pattern = $matcher->compilePattern($rule['expanded_pattern']);
        
        //echo "\n\nrule: {$rule['message']}\n";echo "delimiter: $delimiter\n";echo $delimiter . $value . $delimiter;echo "\n".str_repeat("=", 60) ."\n";print_r($subjects); echo $pattern . "\n";
        
        $explodeAllMatched = true;
        $explodeAnyMatched = false;
        foreach ($subjects as $subject) {
          $isMatch = $matcher->matchCompiled($pattern, $subject);
          
          //echo $rule['expanded_pattern'] . ' negate: ' . (($negate) ? 'true' : 'false') . ', match: ' . (($isMatch) ? 'true' : 'false') . "\n";
          
          if ($negate) {
            $isMatch = !$isMatch;
          }
          
          if ($isMatch) {
            $explodeAnyMatched = true;
            if ($explode == "match-any") {
              break;
            }
          } else {
            $explodeAllMatched = false;
            if ($explode == "match-all") {
              break;
            }
          }
        }
        
        $isMatch = false;
        if ((($explode == "no" || $explode == "match-any") && $explodeAnyMatched) ||
             ($explode == "match-all" && $explodeAllMatched)) {
          $isMatch = true;
        }
        
        if ($isMatch) {
          $quotedAnyMatched = true;
          if ($quoted == "match-any") {
            break;
          }
        } else {
          $quotedAllMatched = false;
          if ($quoted == "match-all") {
            break;
          }
        }
        
      }

      $isMatch = false;
      if ((($quoted == "no" || $quoted == "match-any") && $quotedAnyMatched) ||
           ($quoted == "match-all" && $quotedAllMatched)) {
        $isMatch = true;
      }

      if ($isMatch) {
        $ruleCopy = $rule;
        unset($ruleCopy['expanded_pattern']);
        $result['rules'][] = $ruleCopy;
        $result['score'] += $rule['score'];

        $stop = isset($rule['stop']) ? $rule['stop'] : false;
        if ($stop) {
          break;
        }
      }

    }

    return $result;
  }
  
  protected function write($str) {
    if ($this->debug) {
      echo $str;
    }
  }
}