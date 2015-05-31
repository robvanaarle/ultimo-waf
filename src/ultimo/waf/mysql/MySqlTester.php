<?php
/**
 * Currently this class is just a proof of concept. Many performance
 * optimizations can be applied.
 */
namespace ultimo\waf\mysql;

class MySqlTester {

  public $debug = false;
  
  public function test($value) {
    $lex = new MySqlLexer();

    $lex->run($value);
    $result = $this->isValidMySql($lex);

    if ($result) {
      return true;
    }

    // injection could be within quoted parameter
    // TODO: what if used quote is ', and value is "' or 1='1
    //  firstStringDelimiter() needs to be fixed for this
    $delimiter = $this->getFirstStringDelimiter($value);
    if ($delimiter !== null) {
      $value = $delimiter . $value . $delimiter;
      
      // TODO: reuse lexer
      $lex = new MySqlLexer();

      $lex->run($value);
      $result = $this->isValidMySql($lex);
    }

    return $result;
  }
  
  protected function isValidMySql(MySqlLexer $lexer) {
    // no injection if ony of the following applies *old*
    // - one or more unknown tokens
    // - two successive identifier tokens outside comments
    // - none of the following tokens {keyword, operator, function}
    // - none of the following tokens {identifier, quoted_identifier, string, number} 
      
    // This should work, but is also dangerous in early versions: it could create one or more backdoors
    //if ($counts['unknown'] > 0) {
    //  return false;
    //}
    
    // ideas
    // - not whitespace of any kind = false?
    
    // array('identifier', 'string', 'number', 'hexadecimal', 'quoted_identifier');
    $pattern = array(
      'identifier' => array(
        'identifier' => array(
          'identifier' => null,
         ),
        'number' => null
      ),
      'number' => array(
        'number' => null,
        'identifier' => array(
          'identifier' => null,
          //'number' => null // already defined [identifier -> number]
        )
      ),
      'string' => array(
        'identifier' => array(
          'identifier' => null
        )
      ),
      'operator' => array(
        'operator' => null
      )
    );
    
    // hexadecimal => alphanum
    
    $matcher = new MySqlTokenMatcher();
    $match = $matcher->match($pattern, $lexer->tokens);
    if ($match !== null) {
      $this->write("[pattern matched: ");
      foreach ($match as $token) {
        $this->write("'{$token['value']}' ({$token['type']}), ");
      }
      $this->write("]");
      return false;
    }
    
    $counts = $lexer->counts;
    
    if ($counts['keyword'] == 0 && $counts['operator'] == 0 && $counts['function'] == 0 && $counts['logical_operator'] == 0) {
      $this->write("[no keywords or operators]");
      return false;
    }
    
    if ($counts['identifier'] == 0 && $counts['quoted_identifier'] == 0 && $counts['string'] == 0 && $counts['number'] == 0 && $counts['hexadecimal'] == 0) {
      $this->write("[no identifier of any kind]");
      return false;
    }
    
    return true;
  }
  
  static public function getFirstStringDelimiter($value) {
    if (preg_match('/(?<!\\\\)(\'|")/', $value, $matches)) {
      return $matches[1];
    }
    return null;
  }
  
  protected function write($str) {
    if ($this->debug) {
      echo $str;
    }
  }
}