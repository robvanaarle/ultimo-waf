<?php
/**
 * Currently this class is just a proof of concept. Many performance
 * optimizations can be applied.
 */
namespace ultimo\waf\mysql;

// url encode should already be performed, as this waf is after routing
//   * maybe add urldecode option?
class MySqlLexer {
  public $debug = false;
  public $tokens = array();
  
  protected $lastToken = array();
  protected $value;
  protected $next;
  protected $choppedValue; // rename to remainingValue?
  protected $customTypes = array();
 
  // http://savage.net.au/SQL/sql-2003-2.bnf.html#delimited%20identifier
  
  public function __construct(array $customTypes=array()) {
    $this->setCustomTypes($customTypes);
  }
  
  public function setCustomTypes(array $customTypes) {
    foreach ($customTypes as $name => $values) {
      foreach ($values as $value) {
        $this->customTypes[$value] = $name;
      }
    }
  }
  
  protected function consume($tokenValue, $type) {
    $this->tokens[] = array('value' => $tokenValue, 'type' => $type);
    
    $this->choppedValue = substr($this->choppedValue, strlen($tokenValue));
    $this->next = substr($this->choppedValue, 0, 1);
    $this->write("  {$type} matched: {$tokenValue}\n");
    $this->lastToken = array('value' => $tokenValue, 'type' => $type);
  }
  
  // unused
  protected function undo($tokenIndex) {
    $tokens = array_splice($this->tokens, $tokenIndex, 1);

    if (empty($tokens)) {
      return false;
    }
    
    $this->choppedValue = $tokens[0]['value'] . $this->choppedValue;
    $this->next = substr($this->choppedValue, 0, 1);
    return true;
  }
  
  protected function match($regex, $type, $index=0) {
    if (preg_match("/^" . $regex . "/s", $this->choppedValue, $matches)) {
      $this->consume($matches[$index], $type);
      return true;
    }
    return false;
  }
 
  protected function consumeNumber() {
    if ($this->match(
            // 1 or more digits, decimal seperator followed 1 or more digits, 1
            // or more digits followed by decimal seperator followed by 1 or
            // more digits
            "(([0-9]*\.[0-9]+|[0-9]+)(e[\-]?[\d]+)?)"
            // number is followed by end of string or any not alphanumberic
            // character, _, . or $ (then it is an identifier)
            . "($|[^a-z0-9_\.\$])"
        , "number", 1)) {
      return true;
    }
  
    return false;
  }

  protected function consumeString($delimiter="\"") {

    $escapedDelimiter = preg_quote($delimiter, '/');
    // http://stackoverflow.com/questions/5695240/php-regex-to-ignore-escaped-quotes-within-quotes
    if ($this->match($escapedDelimiter . '[^'.$escapedDelimiter.'\\\\]*(?:\\\\.[^'.$escapedDelimiter.'\\\\]*)*' . $escapedDelimiter, 'string')) {
      return true;
    }
    
    return false;
  }
 
  protected function consumeQuotedIdentifier() {
    return $this->match('`[^`]+`', 'quoted_identifier');
  }
  
 
  protected function consumeWhitespace() {
    return $this->match("[\s\x0b]+", 'whitespace');
  }
 
  protected function consumeIdentifier() {      
    if (preg_match("/^[a-z0-9_\$]*[a-z_\$]+[a-z0-9_\$]*/", $this->choppedValue, $matches)) {
      $word = $matches[0];
      
      $this->consume($word, 'identifier');
      return true;
    }
 
    return false;
  }
 
  protected function consumeComment() {
    //https://dev.mysql.com/doc/refman/5.7/en/comments.html
    
    if ($this->next == '*') {
      if ($this->match('\*\/', 'comment-end')) {
        return true;
      }
    } else if ($this->next == '#' || $this->next == '-') {
      if ($this->match('(#.*?($|\n)|\-\-($|\h|\x0c|\x0d|\x0b).*?($|\n)|\-\-\n)', 'comment', 1)) {
        return true;
      }
    } 
 
    // /*!(\d+)? ... */
    if (preg_match("/^\/\*\!([\d]+)?/", $this->choppedValue, $matches)) {
      if (isset($matches[1])) {
        $this->consume("/*!" . $matches[1], 'conditional-comment-start');
      } else {
        $this->consume("/*!", 'executed-comment-start');
      }
      return true;
    }
  
    if ($this->match('\/\*.*?\*\/', 'comment')) {
      return true;
    }
 
    return false;
  }
 
  protected function consumeHexadecimal() {
    if ($this->next == 'x') {
      return $this->match('x\'([0-9a-f][0-9a-f])*\'', 'hexadecimal');
    } else {
      return $this->match('0x([0-9a-f])+', 'hexadecimal');
    }
  }
  
  protected function consumeBitField() {
    return $this->match('b\'[01]*\'', 'bit-field');
  }
 
  protected function consumeUnknown() {
    $this->match("(.+?)($|,|;|\(|\)|\s|\x0b)", 'unknown', 1);
  }
 
  protected function consumeVariable() {
    if ($this->match("@@[0-9a-z_\.\$]+", "system-variable")) {
      return true;
    }
    
    $delimiter = substr($this->choppedValue, 1, 1);
    if (in_array($delimiter, array('"', "'"))) {
      $escapedDelimiter = preg_quote($delimiter, '/');
      // http://stackoverflow.com/questions/5695240/php-regex-to-ignore-escaped-quotes-within-quotes
      return $this->match('@' . $escapedDelimiter . '[^'.$escapedDelimiter.'\\\\]*(?:\\\\.[^'.$escapedDelimiter.'\\\\]*)*' . $escapedDelimiter, 'user-defined-variable');
    } elseif ($delimiter == '`') {
      return $this->match('@' . "`[^`]+`", 'user-defined-variable');
    } else {
      return $this->match('@[a-z0-9_\$\.]*', 'user-defined-variable');
    }
  }
  
  // TODO: make this much more efficient by pre sorting the custom types
  public function consumeCustomType() {
    $word = null;
      
    $alphaNumericTokens = array();
    $specialTokens = array();
    foreach ($this->customTypes as $token => $type) {
      if (preg_match('/^[a-z0-9_\$]+/', $token)) {
        $alphaNumericTokens[$token] = $type;
      } else {
        $specialTokens[$token] = $type;
      }
    }

    ksort($specialTokens);
    $specialTokens = $specialTokens;
    
    // special tokens
    if (count($specialTokens) > 0) {
      $quotedTokens = array();
      foreach ($specialTokens as $token => $type) {
        $quotedTokens[] = preg_quote($token, '/');
      }
      
      // add comments tokens
      $quotedTokens[] = '\/\*';
      $quotedTokens[] = '\-\-($|[\s\x0b])';
      $quotedTokens[] = '#';
      
      
      // array was sorted from short to long tokens, matching should the the other way around. Comment tokens are in front now, as they are more important than other tokens
      $quotedTokens = array_reverse($quotedTokens);
      
      $pattern = '/^(' . implode('|', $quotedTokens) . ')/';
      
      //echo $pattern . "\n";
      //echo $this->choppedValue . "\n";
      
      if (preg_match($pattern, $this->choppedValue, $matches)) {
        $token = $matches[0];
        //echo "Match: {$token}\n";
        if (!isset($specialTokens[$token])) {
          // comment token
          return false;
        }
        $this->consume($token, $specialTokens[$token]);
        return true;
      }
    }
    
    
    // alpha numeric tokens
    if (preg_match("/^[a-z0-9_\$]*[a-z_\$]+[a-z0-9_\$]*/", $this->choppedValue, $matches)) {
      $token = $matches[0];
      if (isset($alphaNumericTokens[$token])) {
        $this->consume($token, $alphaNumericTokens[$token]);
        return true;
      }
    }
    
    return false;
  }
  
  public function consumeCustomTypeOld() {
    $word = null;
    
    // special chars only, for values like <, > and <=>
    if (preg_match("/^([^a-z0-9\_\$\s\x0b\'\"]+?)"
                 . "($|\/\*|\-\-($|[\s\x0b])|\#|[a-z0-9\_\$\s\x0b\"\'\`])/" // must be followed by end, comment or non operator character
                 , $this->choppedValue, $matches)) {
      $word = $matches[1];
    }
    
    // alpha numeric tokens
    if ($word === null && preg_match("/^[a-z0-9_\$]*[a-z_\$]+[a-z0-9_\$]*/", $this->choppedValue, $matches)) {
      $word = $matches[0];
    }
    
    if ($word !== null) {
      if (isset($this->customTypes[$word])) {
        $this->consume($word, $this->customTypes[$word]);
        return true;
      }
    }
    
    return false;
  }
  
  protected function write($str) {
    if ($this->debug) {
      echo $str;
    }
  }
  
  public function run($value) {
    $this->tokens = array();
    $this->value = strtolower($value);
    $this->next = substr($this->value, 0, 1);
    $this->choppedValue = $this->value;
  
    $i = 0;
    while(strlen($this->choppedValue) > 0) {
      $i++;
      
      // temporary failsafe
      if ($i > 1000) {
        $this->write("ERROR: too many steps, error in lexer?");
        echo "ERROR: too many steps, error in lexer? ($value)";
        exit();
      }
     
      $this->write("next: '{$this->next}'\n");
    
      if ($this->consumeCustomType()) {
        continue;
      }
      
      if (in_array($this->next, array('0', 'x'))) {
        if ($this->consumeHexadecimal()) {
          continue;
        }
      } elseif (in_array($this->next, array('b'))) {
        if ($this->consumeBitField()) {
          continue;
        }
      }
     
      // TODO: test if switch is faster
      if (in_array($this->next, array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '.'))) {
        if ($this->consumeNumber()) {
          continue;
        }
      } elseif (in_array($this->next, array(' ', "\n", "\t", "\r", urldecode("%0b"), urldecode("%0c")))) {
        if ($this->consumeWhitespace()) {
          continue;
        }
      } elseif (in_array($this->next, array('"', '\''))) {
        if ($this->consumeString($this->next)) {
          continue;
        }
      } elseif ($this->next == '`') {
        $this->consumeQuotedIdentifier();
        continue;
      /*} elseif ($this->next == '(') {
        $this->consume('(', 'char-parenthesis-left');
        continue;
      } elseif ($this->next == ')') {
        $this->consume(')', 'char-parenthesis-right');
        continue;
      } elseif ($this->next == ',') {
        $this->consume(',', 'char-comma');
        continue;
      } elseif ($this->next == ';') {
        $this->consume(';', 'char-semicolon');
        continue;
      } elseif ($this->next == '.') {
        $this->consume('.', 'char-period');
        continue;*/
      } elseif ($this->next == '@') {
        if ($this->consumeVariable()) {
          continue;
        }
      }
   
      if (in_array($this->next, array('#', '-', '/', '*'))) {
        if ($this->consumeComment()) {
          continue;
        }
      }

      if ($this->consumeIdentifier()) {
        continue;
      }
   
      $this->consumeUnknown();
    }
  
    return $this->tokens;
  }

}