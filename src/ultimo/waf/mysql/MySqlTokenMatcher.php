<?php

namespace ultimo\waf\mysql;

class MySqlTokenMatcher {

  
  public function match($tokenPattern, array $tokens) {
    
    // convert tokens to subject
    $inConditionalComment = false;
    foreach ($tokens as $token) {
      if (in_array($token['type'], array('comment', 'executed_comment_start'))) {
        continue;
      } elseif ($token['type'] == 'comment_end') {
        $inConditionalComment = false;
        continue;
      }
      
      if ($inConditionalComment) {
        continue;
      }
      
      $tokenTypes[] = '%' . $token['type'];
    }
    $subject = ' ' . implode(' ', $tokenTypes);
    
    // convert token pattern to regex pattern
    $elems = explode(' ', $tokenPattern);
    
    $pattern = array();
    foreach ($elems as $elem) {
      $elem = trim($elem);
      if (strlen($elem) == 0) {
        continue;
      }
      
      $elem = preg_replace('/\%[a-z0-9\.\_]+/', '( \\\\$0)', $elem);
      
      $pattern[] = $elem;
    }
    
    $pattern = implode('', $pattern);

    //echo "\n'{$pattern}'\n'{$subject}'\n";
    
    return preg_match($pattern, $subject) == 1;
  }
}