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
      } elseif ($token['type'] == 'comment-end' || ($index+1 >= $tokenCount)) {
        if ($conditionalCommentStartIndex !== null) {
          // found the end of a conditional comment
          
          // part before the comment is always executed
          // append conditional-comment-start, as this acts as whitespace
          $beforeAndStartCommentSubject = $this->tokensToSubject($tokens, $offset, $conditionalCommentStartIndex-$offset+1);
          
          
          // check if this is the last token
          if ($index+1 < $tokenCount) {
            $commentEndSubject = $this->tokensToSubject($tokens, $index, 1);
            // call this function recursively, and prepend the two paths to all paths from the recursive call
            $recursivePaths = $this->explodeConditionalCommentPaths($tokens, $index+1);
            
            // contents of conditional comment may or may not be executed, these are the two paths
            $commentContentSubject = $this->tokensToSubject($tokens, $conditionalCommentStartIndex+1, $index-$conditionalCommentStartIndex-1);
            
          } else {
            // this is the end of the value, no comment-end or recursive paths are present
            $commentEndSubject = '';
            $recursivePaths = array('');
            
            // contents of conditional comment may or may not be executed, these are the two paths
            $commentContentSubject = $this->tokensToSubject($tokens, $conditionalCommentStartIndex+1, $index-$conditionalCommentStartIndex);
          }
          
          
          foreach ($recursivePaths as $recursivePath) {
            // create a path without comment content
            $tokenPaths[] = $beforeAndStartCommentSubject . $commentEndSubject . $recursivePath;
            
            // create a path with comment content
            $tokenPaths[] = $beforeAndStartCommentSubject . $commentContentSubject . $commentEndSubject . $recursivePath;
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
    //$ignoredTypes = array('comment', 'executed-comment-start', 'conditional-comment-start', 'comment-end');
    
    if ($length == -1) {
      $end = count($tokens);
    } else {
      $end = $offset+$length;
    }
    
    $tokenTypes = array('');
    for ($index = $offset; $index < $end; $index++) {
      $token = $tokens[$index];
      
      //if (in_array($token['type'], $ignoredTypes)) {
      //  continue;
      //}
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
    
    return preg_replace('/%[^%\\\\]*(?:\\\\.[^%\\\\]*)*%/', '( $0)', str_replace(' ', '', $tokenPattern));
    //return preg_replace('/%[^%]+?%/', '( $0)', str_replace(' ', '', $tokenPattern));
  }
  
  public function matchCompiled($compiledPattern, $compiledSubject) {
    return preg_match($compiledPattern, $compiledSubject);
  }
  
  //TODO: 
  // - implicit whitespace (default=false)
  // - explode cc paths (default=false)
  // - ignore comments (default=true)
  public function match($tokenPattern, array $tokens, $explodeCCPaths = false) {
    
    $subjects = $this->compileSubject($tokens, $explodeCCPaths);
    
    $pattern = $this->compilePattern($tokenPattern);
    
    //print_r($subjects); echo $pattern . "\n";
    
    foreach ($subjects as $subject) {
      if (preg_match($pattern, $subject)) {
        return true;
      }
    }
    return false;
  }
}