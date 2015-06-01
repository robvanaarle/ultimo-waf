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
  protected $customTypes;
  
  // conditional comment depth
  //protected $commentStack = array();
 
  // http://savage.net.au/SQL/sql-2003-2.bnf.html#delimited%20identifier
  // [x] single quoted string
  // [x] double quoted string
  // [x] backticked string
  // [x] number
  // [x] keyword
  // [x] parenthesis
  // [\] operator
  // [\] comparison (operator?)
  // [-] function
  // [\] comment
  //     /*! syntax
  // [-] list (WHERE IN (x, y, z))
  // [x] identifier
  // [x] unknown
  // overig:
  //  , := extra weight if between identifiers, numbers, strings, ...?
  //  ;
  //  * := operator?
  // [x] binary b'1001'
  // [x] octal or others?
  // [x] user-defined variables @
  // [x] @@ system variables
 
  // configurable grammar
  // add scores/weights to each type of token
  //   right_parenthesis >= left_parenthesis -> +42
  // add scores/weights by surrounding type of tokens
  //  eg a comma between identifier, string or number weighs more than a comma between 'invalid' tokens
  
  public function __construct(array $customTypes=array()) {
    $this->customTypes = $customTypes;
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
    // FIXED: number may not be followed by a alpha char(?), e.g. 9gag should
    // not consume 9 as number
    //  this reduces false positives
    
    // test cases: 1 12 12.3 12.34 .0
    //             1.1.1 11a 11.11a
    
    
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

    $escapedDelimiter = preg_quote($delimiter);
    // http://stackoverflow.com/questions/5695240/php-regex-to-ignore-escaped-quotes-within-quotes
    if ($this->match($escapedDelimiter . '[^'.$escapedDelimiter.'\\\\]*(?:\\\\.[^'.$escapedDelimiter.'\\\\]*)*' . $escapedDelimiter, 'string')) {
      return true;
    }
    
    return false;
  }
 
  protected function consumeQuotedIdentifier() {
    return $this->match('`[^`]+`', 'quoted_identifier');
  }
 
  protected function consumeLogicalOperator() {
    //$logicalOperators = array('and', '&&', 'or', '||', 'xor');
    if ($this->match("(\&\&|\|\|)", 'logical_operator', 1)) {
      return true;
    }
    
    return $this->match("(and|or|xor)($|[^a-z0-9_])", 'logical_operator', 1);
  }
  
  protected function consumeOperator() {
    // array('&&', '=', ':=', '&', '~', '|', '^', '/', '<=>', '>=', '>', '<<', '<=', '<', '-', '%', '...', '!=', '<>', '!', '||', '+', '>>', '*')

    // also contains the logical operators
    // TODO: remove 'followed by non-operator character', as this is about syntax, not tokens
    return $this->match(
              "(&&|\=|\:\=|&|~|\||\^|\/|\<\=\>|\>\=|\>|\<\<|\<\=|\<|\-|%|\.\.\.|\!\=|\<\>|\!|\|\||\+|\>\>|\*)" // the valid operators
            . "($|\/\*|\-\-($|[\s\x0b])|[^&|\=|\:|~|\||\^|\/|\<|\>|\-|%|\.|\!|\||\+|\*])" // must be followed by end, comment or non operator character
            , 'operator', 1);
  }
 
  protected function consumeWhitespace() {
    return $this->match("[\s\x0b]+", 'whitespace');
  }
 
  protected function consumeIdentifier() {      
    if (preg_match("/^[a-z0-9_\$]*[a-z_\$]+[a-z0-9_\$]*/", $this->choppedValue, $matches)) {
      $word = $matches[0];
      
      $this->consume($word, 'identifier');
      return true;
   
      // TODO: add more keywords?
      // TODO: consume names of popular functions
      // TODO: remove keywords, as it's not a token: they consist of operators, functions and other types
     
      // http://dev.mysql.com/doc/mysqld-version-reference/en/mysqld-version-reference-reservedwords-5-7.html
      // only dangerous keywords are checked against, to reduce false positives
      //$keywords = array('select', 'union', 'update', 'delete', 'insert', 'table', 'from', 'drop', 'group', 'null', 'and', 'or');
      
      // TODO: store this static
      // contains logical operators
      $keywords = array(
        'accessible', 'add', 'all', 'alter', 'analyze', 'and',
        'as', 'asc', 'asensitive', 'before', 'between', 'bigint', 'binary',
        'blob', 'both', 'by', 'call', 'cascade', 'case', 'change', 'char',
        'character', 'check', 'collate', 'column', 'condition', 'constraint',
        'continue', 'convert', 'create', 'cross', 'current_date', 'current_time',
        'current_timestamp', 'current_user', 'cursor', 'database', 'databases',
        'day_hour', 'day_microsecond', 'day_minute', 'day_second', 'dec',
        'decimal', 'declare', 'default', 'delayed', 'delete', 'desc', 'describe',
        'deterministic', 'distinct', 'distinctrow', 'div', 'double', 'drop',
        'dual', 'each', 'else', 'elseif', 'enclosed', 'escaped', 'exists', 'exit',
        'explain', 'false', 'fetch', 'float', 'float4', 'float8', 'for', 'force',
        'foreign', 'from', 'fulltext', 'generated', 'get', 'grant', 'group',
        'having', 'high_priority', 'hour_microsecond', 'hour_minute', 'hour_second',
        'if', 'ignore', 'in', 'index', 'infile', 'inner', 'inout', 'insensitive',
        'insert', 'int', 'int1', 'int2', 'int3', 'int4', 'int8', 'integer',
        'interval', 'into', 'io_after_gtids', 'io_before_gtids', 'is', 'iterate',
        'join', 'key', 'keys', 'kill', 'leading', 'leave', 'left', 'like',
        'limit', 'linear', 'lines', 'load', 'localtime', 'localtimestamp', 'lock',
        'long', 'longblob', 'longtext', 'loop', 'low_priority', 'master_bind',
        'master_ssl_verify_server_cert', 'match', 'maxvalue', 'mediumblob',
        'mediumint', 'mediumtext', 'middleint', 'minute_microsecond', 'minute_second',
        'mod', 'modifies', 'natural', 'nonblocking', 'not', 'no_write_to_binlog',
        'null', 'numeric', 'on', 'optimize', 'optimizer_costs', 'option',
        'optionally', 'or', 'order', 'out', 'outer', 'outfile', 'parse_gcol_expr',
        'partition', 'precision', 'primary', 'procedure', 'purge', 'range', 'read',
        'reads', 'read_write', 'real', 'references', 'regexp', 'release', 'rename',
        'repeat', 'replace', 'require', 'resignal', 'restrict', 'return', 'revoke',
        'right', 'rlike', 'schema', 'schemas', 'second_microsecond', 'select',
        'sensitive', 'separator', 'set', 'show', 'signal', 'smallint', 'spatial',
        'specific', 'sql', 'sqlexception', 'sqlstate', 'sqlwarning', 'sql_big_result',
        'sql_calc_found_rows', 'sql_small_result', 'ssl', 'starting', 'stored',
        'straight_join', 'table', 'terminated', 'then', 'tinyblob', 'tinyint',
        'tinytext', 'to', 'trailing', 'trigger', 'true', 'undo', 'union', 'unique',
        'unlock', 'unsigned', 'update', 'usage', 'use', 'using', 'utc_date',
        'utc_time', 'utc_timestamp', 'values', 'varbinary', 'varchar', 'varcharacter',
        'varying', 'virtual', 'when', 'where', 'while', 'with', 'write','xor',
        'year_month', 'zerofill'
      );
      
      if (in_array($word, $keywords)) {
        $this->consume($word, 'keyword');
      } elseif (in_array($word, array('concat', 'concat_ws', 'ascii', 'hex', 'unhex', 'sleep', 'md5', 'benchmark', 'not_regexp'))) {
        // to reduce false positives, only functions popular for MySQL injection are matched
        $this->consume($word, 'function');
      } elseif (in_array($word, array('boolean'))) {
        $this->consume($word, 'modifier');
      } else {
        $this->consume($word, 'identifier');
      }
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
    $this->match("(.*?)(?:$|,|\s|\x0b)", 'unknown', 1);
  }
 
  protected function consumeVariable() {
    if ($this->match("@@[0-9a-z_\.\$]+", "system-variable")) {
      return true;
    }
    
    $delimiter = substr($this->choppedValue, 1, 1);
    if (in_array($delimiter, array('"', "'"))) {
      $escapedDelimiter = preg_quote($delimiter);
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
    
    // special chars only, for values like <, > and <=>
    if (preg_match("/^([^a-z0-9\_\$\s\x0b\'\"]+?)"
                 . "($|\/\*|\-\-($|[\s\x0b])|\#|[a-z0-9\_\$\s\x0b\"\'\`])/" // must be followed by end, comment or non operator character
                 , $this->choppedValue, $matches)) {
      $word = $matches[1];
    }
    
    
    // alpha numeric
    if ($word === null && preg_match("/^([^\s\x0b\'\"]+)/", $this->choppedValue, $matches)) {
      $word = $matches[0];
    }
    
    if ($word !== null) {
      foreach ($this->customTypes as $name => $values) {
        if (in_array($word, $values)) {
          $this->consume($word, $name);
          return true;
        }
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
      if ($i > 100) {
        $this->write("ERROR: many steps, error in lexer?");
        exit();
      }
     
      $this->write("next: '{$this->next}'}\n");
    
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
      } elseif ($this->next == '(') {
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
   
      /*if (in_array($this->next, array('a', '&', 'o', '|', 'x'))) {
        if ($this->consumeLogicalOperator()) {
          continue;
        }
      }*/
      
      /*if (in_array($this->next, array('&', '=', ':', '~', '|', '^', '/', '<', '>', '-', '%', '.', '!', '|', '+', '*'))) {
        if ($this->consumeOperator()) {
          continue;
        }
      }*/
      
      if ($this->next == '.') {
        $this->consume('.', 'char-period');
        continue;
      }
     
      if ($this->consumeIdentifier()) {
        continue;
      }
   
      $this->consumeUnknown();
    }
  
    return $this->tokens;
  }

}