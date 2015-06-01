<?php

namespace ultimo\waf\mysql;

class MySqlTokenMatcher {

  protected function explodeConditionalCommentPaths(array $tokens, $offset=0) {
    $tokenCount = count($tokens);

    $conditionalCommentStartIndex = null;
    $inComment = false;
    
    $tokenPaths = array();
    // find the first conditional comment
    for($index = $offset; $index < $tokenCount; $index++) {
      $token = $tokens[$index];
      
      if ($token['type'] == 'conditional-comment-start') {
        if (!$inComment && $conditionalCommentStartIndex === null) {
          $conditionalCommentStartIndex = $index;
          $inComment = true;
        }
      } elseif ($token['type'] == 'executed-comment-start') {
        $inComment = true;
      } elseif ($token['type'] == 'comment-end') {
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
  
  protected function tokensToSubject(array $tokens, $offset=0, $length=-1) {
    $ignoredTypes = array('comment', 'executed-comment-start', 'conditional-comment-start', 'comment-end');
    
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
      $tokenTypes[] = '%' . $token['type'] . '%';
    }
    return implode(' ', $tokenTypes);
  }
  
  public function compileSubject(array $tokens, $explodeCCPaths) {
    // convert tokens to subject
    if ($explodeCCPaths) {
      return $this->explodeConditionalCommentPaths($tokens);
    } else {
      return array($this->tokensToSubject($tokens));
    }
  }
  
  public function compilePattern($tokenPattern) {
    // convert token pattern to regex pattern: remove whitespace and add a single space before each token type
    return preg_replace('/%[a-z0-9\.\_]+%/', '( \\\\$0)', str_replace(' ', '', $tokenPattern));
  }
  
  //TODO: 
  // - implicit whitespace (default=false)
  // - explode cc paths (default=false)
  // - ignore comments (default=true)
  public function match($tokenPattern, array $tokens, $explodeCCPaths = false) {
    
    $subjects = $this->compileSubject($tokens, $explodeCCPaths);
    
    $pattern = $this->compilePattern($tokenPattern);
    
    foreach ($subjects as $subject) {
      if (preg_match($pattern, $subject)) {
        return true;
      }
    }
    return false;
  }
}