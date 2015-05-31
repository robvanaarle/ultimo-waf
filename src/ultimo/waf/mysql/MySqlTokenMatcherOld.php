<?php

namespace ultimo\waf\mysql;

class MySqlTokenMatcherOld {
  
  // TODO: max nesting of comments to prevent performance problems => very suspicious
  // TODO: find a way to remove recursiveness for better performance
  public function match(array $pattern, array $tokens) {
    $ccStack = array(0 => true);
    $index = 0;
    $token = null;
    while (!empty($tokens)) {
      if ($token !== null) {
        if ($token['type'] == 'conditional_comment_end') {
          array_pop($ccStack);
          
          // TODO: work with offset
          $token = array_shift($tokens);
          $index++;
          continue;
        } elseif ($token['type'] == 'conditional_comment_start') {
          $ccStack[] = true;
          
          // TODO: work with offset
          $token = array_shift($tokens);
          $index++;
          continue;
        }
      }
      
      $match = $this->matchRecursive($pattern, $tokens, array(), $ccStack);
      if ($match !== null) {
        return $match;
      }

      // TODO: work with offset
      $token = array_shift($tokens);
      $index++;
    }
  }
  
  protected function matchRecursive(array $pattern=null, array $tokens, array $match, array $ccStack) {
    if (empty($pattern)) {
      return $match;
    }
    
    if (empty($tokens)) {
      return null;
    }
    
    // TODO: work with offset
    $token = array_shift($tokens);
    
    if ($token['type'] == 'conditional_comment_end') {
      // the current conditional comment line has ended, pop it from the stack
      array_pop($ccStack);
      return $this->matchRecursive($pattern, $tokens, $match, $ccStack);
    }
    
    if ($token['type'] == 'conditional_comment_start') {
      // 2 paths emerge, as the code in the comment could be executed or not
      
      // path 1: don't execute code in comment
      $childCcStack = $ccStack;
      $childCcStack[] = false;
      $childMatch = $this->matchRecursive($pattern, $tokens, $match, $childCcStack);
      if ($childMatch !== null) {
        return $childMatch;
      }
      
      // path 2: execute code in comment
      // unnecessary, as matchTokenPatternRecursive() loops through each token
      // and eventually starts at this point?? No, because one or more tokens
      // of the pattern could already have matched preceding tokens
      $childCcStack = $ccStack;
      $childCcStack[] = true;
      return $this->matchRecursive($pattern, $tokens, $match, $childCcStack);
    }
    
    // test what the current conditional comment stack is
    if (count($ccStack) == 0) {
      echo "Empty CC Stack, unable to determine how to continue: should never happen";
      print_r($tokens);
      print_r($match);
      exit();
    }
    if (!$ccStack[count($ccStack)-1]) {
      return $this->matchRecursive($pattern, $tokens, $match, $ccStack);
    }
    
    // ignore all whitespaces
    if (in_array($token['type'], array('whitespace', 'executed_comment_start', 'executed_comment_end', 'unexpected_comment_end', 'comment'))) {
      //$match[] = $token;
      return $this->matchRecursive($pattern, $tokens, $match, $ccStack);
    }
    
    // next loop could also be implemented recursive, but a loop has better performance
    foreach ($pattern as $type => $childPattern) {
      if ($token['type'] == $type) {
        $childMatch = $match;
        $childMatch[] = $token;
        $childMatch = $this->matchRecursive($childPattern, $tokens, $childMatch, $ccStack);
        
        if ($childMatch !== null) {
          return $childMatch;
        }
      }
    }
    
    return null;
  }
}