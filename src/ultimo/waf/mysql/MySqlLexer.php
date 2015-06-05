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
  protected $alphaNumericTokens;
  protected $specialTokens;
  protected $specialTokensPattern;
 
  // http://savage.net.au/SQL/sql-2003-2.bnf.html#delimited%20identifier
  
  public function __construct(array $customTypes=array()) {
    $this->setCustomTypes($customTypes);
  }
  
  protected function setCustomTypes(array $customTypes) {
    $this->alphaNumericTokens = array();
    $this->specialTokens = array();
    foreach ($customTypes as $type => $values) {
      foreach ($values as $value) {
        if (preg_match('/^[a-z0-9_\$]+/', $value)) {
          $this->alphaNumericTokens[$value] = $type;
        } else {
          $this->specialTokens[$value] = $type;
        }
      }
    }

    // Sort tokens by value. This is reversed later, so for example << is matched before <
    ksort($this->specialTokens);
    
    // precompile pattern for special tokens

    $quotedTokens = array();
    foreach ($this->specialTokens as $value => $type) {
      $quotedTokens[] = preg_quote($value, '/');
    }
      
    // add comment token values, after reversal they have highest priority
    $quotedTokens[] = '\/\*';
    $quotedTokens[] = '\-\-($|[\s\x0b])';
    $quotedTokens[] = '#';
      
      
    // array was sorted from short to long tokens, matching should the the other way around. Comment tokens are in front now, as they are more important than other tokens
    $quotedTokens = array_reverse($quotedTokens);
      
    $this->specialTokensPattern = '/^(' . implode('|', $quotedTokens) . ')/';
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

  protected function consumeString() {
    $escapedDelimiter = preg_quote($this->next, '/');
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
 
  protected function consumeCommentEnd() {
    return $this->match('\*\/', 'comment-end');
  }

  protected function consumeComment() {
    return $this->match('(#.*?($|\n)|\-\-($|\h|\x0c|\x0d|\x0b).*?($|\n)|\-\-\n)', 'comment', 1);
  }

  protected function consumeExecutableComment() {
    //https://dev.mysql.com/doc/refman/5.7/en/comments.html
 
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
  
  public function consumeCustomType() {
    $word = null;
          
    if (preg_match($this->specialTokensPattern, $this->choppedValue, $matches)) {
      $token = $matches[0];
      //echo "Match: {$token}\n";
      if (!isset($this->specialTokens[$token])) {
        // comment token
        return false;
      }
      $this->consume($token, $this->specialTokens[$token]);
      return true;
    }
    
    
    // alpha numeric tokens
    if (preg_match("/^[a-z0-9_\$]*[a-z_\$]+[a-z0-9_\$]*/", $this->choppedValue, $matches)) {
      $token = $matches[0];
      if (isset($this->alphaNumericTokens[$token])) {
        $this->consume($token, $this->alphaNumericTokens[$token]);
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
  
    $x0b = urldecode("%0b");
    $x0c = urldecode("%0c");

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
      
      switch($this->next) {
        case '0':
          if ($this->consumeHexadecimal() || $this->consumeNumber()) {
            continue 2;
          }
          break;
        case '1':
        case '2':
        case '3':
        case '4':
        case '5':
        case '6':
        case '7':
        case '8':
        case '9':
          if ($this->consumeNumber()) {
            continue 2;
          }
          break;
        case 'b':
          if ($this->consumeBitField()) {
            continue 2;
          }
          break;
        case 'x':
          if ($this->consumeHexadecimal()) {
            continue 2;
          }
          break;
        case ' ':
        case "\n":
        case "\t":
        case "\r":
        case $x0b:
        case $x0c:
          if ($this->consumeWhitespace()) {
            continue 2;
          }
          break;
        case '"':
        case "'":
          if ($this->consumeString()) {
            continue 2;
          }
          break;
        case '`':
          if ($this->consumeQuotedIdentifier()) {
            continue 2;
          }
          break;
        case '@':
          if ($this->consumeVariable()) {
            continue 2;
          }
          break;
        case '#':
        case '-':
          if ($this->consumeComment()) {
            continue 2;
          }
          break;
        case '/':
          if ($this->consumeExecutableComment()) {
            continue 2;
          }
          break;
        case '*':
          if ($this->consumeCommentEnd()) {
            continue 2;
          }
          break;
      }
     
      if ($this->consumeIdentifier()) {
        continue;
      }
   
      $this->consumeUnknown();
    }
  
    return $this->tokens;
  }

}