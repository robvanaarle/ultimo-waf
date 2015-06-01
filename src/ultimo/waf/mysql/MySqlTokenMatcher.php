<?php

namespace ultimo\waf\mysql;

class MySqlTokenMatcher {

  public function explodeConditionalCommentPaths(array $tokens, $offset=0) {
    
    
    $tokenCount = count($tokens);
    if ($offset >= $tokenCount) {
      return array();
    }
    
    $conditionalCommentStartIndex = null;
    $inComment = false;
    
    $tokenPaths = array();
    // find the first conditional comment
    for($index = $offset; $index < $tokenCount; $index++) {
      $token = $tokens[$index];
      
      if ($token['type'] == 'conditional_comment_start') {
        if (!$inComment && $conditionalCommentStartIndex === null) {
          $conditionalCommentStartIndex = $index;
          $inComment = true;
        }
      } elseif ($token['type'] == 'executed_comment_start') {
        $inComment = true;
      } elseif ($token['type'] == 'comment_end') {
        if ($conditionalCommentStartIndex !== null) {
          // found the end of a conditional comment
          
          // part before the comment is always executed
          $beforeCommentSubject = $this->tokensToSubject($tokens, $offset, $conditionalCommentStartIndex-$offset+1);
          
          // contents of conditional comment may or may not be executed, these are the two paths
          $conditionalCommentSubject = $this->tokensToSubject($tokens, $conditionalCommentStartIndex, $index-$conditionalCommentStartIndex+1);
          
          // call this function recursively, and prepend the two paths to all paths from the recursive call
          $recursivePaths = $this->explodeConditionalCommentPaths($tokens, $index+1);
          foreach ($recursivePaths as $recursivePath) {
            $tokenPaths[] = $beforeCommentSubject . $recursivePath;
            $tokenPaths[] = $beforeCommentSubject . $conditionalCommentSubject . $recursivePath;
          }
          
          return $tokenPaths;
        } else {
          // end of executed comment
          $inComment = false;
        }
      }
    }
    
    // no conditional comment found, just return the single path
    return array($this->tokensToSubject($tokens, $offset, -1));
  }
  
  
  public function tokensToSubject(array $tokens, $offset=0, $length=-1) {
    $ignoredTypes = array('comment', 'executed_comment_start', 'conditional_comment_start', 'comment_end');
    
    if ($length == -1) {
      $end = count($tokens);
    } else {
      $end = $offset+$length;
    }
    
    $tokenTypes = array('');
    for ($index = $offset; $index < $end; $index++) {
      $token = $tokens[$index];
      
      if (in_array($token['type'], $ignoredTypes)) {
        continue;
      }
      $tokenTypes[] = '%' . $token['type'];
    }
    return implode(' ', $tokenTypes);
  }
  
  //TODO: 
  // - implicit whitespace (default=false)
  // - explode cc paths (default=false)
  // - ignore comments (default=true)
  public function match($tokenPattern, array $tokens, $explodeCCPaths = false) {
    
    // convert tokens to subject
    if ($explodeCCPaths) {
      $subjects = $this->explodeConditionalCommentPaths($tokens);
    } else {
      $subjects = array($this->tokensToSubject($tokens));
    }
    
    // convert token pattern to regex pattern: remove whitespace and a single space before each token type
    $pattern = preg_replace('/\%[a-z0-9\.\_]+/', '( \\\\$0)', str_replace(' ', '', $tokenPattern));
    
    foreach ($subjects as $subject) {
      if (preg_match($pattern, $subject)) {
        return true;
      }
    }
    return false;
  }
}