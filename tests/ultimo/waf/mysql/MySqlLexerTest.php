<?php

namespace ultimo\waf\mysql;

class MySqlLexerTest extends \PHPUnit_Framework_TestCase {
  protected $lexer;
  
  protected function setUp() {
    $this->lexer = new MySqlLexer();
  }
  
  public function providerAlphaNumbericCharacters() {
    return array(
      array('a'),
      array('_'),
      array('0'),
      array('1')
    );
  }
  
  public function providerNumericCharacters() {
    return array(
      array('0'),
      array('1')
    );
  }
  
  public function providerAlphaCharacters() {
    return array(
      array('a'),
      array('_')
    );
  }
  
  public function providerWhitespaceCharacters() {
    return array(
      array("\n"), // %0A
      array("\t"), // %09
      array("\r"), // %0D
      array(urldecode("%0B")),
      array(urldecode("%0C"))
    );
  }
  
  public function providerHorizontalWhitespaceCharacters() {
    return array(
      array("\t"), // %09
      array("\r"), // %0D
      array(urldecode("%0B")),
      array(urldecode("%0C"))
    );
  }
  
  public function providerQuoteCharacters() {
    return array(
      array("'"),
      array('"')
    );
  }
  
  /*****************************************************************************
   * Number
   ****************************************************************************/
  
  public function testNumberCanBeAnInteger() {
    $this->assertContains(
      array('type' => 'number', 'value' => '42'),
      $this->lexer->run('42')
    );
  }
  
  public function testNumberCanBeAFloat() {
    $this->assertContains(
      array('type' => 'number', 'value' => '42.1337'),
      $this->lexer->run('42.1337')
    );
  }
  
  /**
   * @dataProvider providerWhitespaceCharacters
   */
  function testNumberCanBeFollowedByWhitespace($whitespace) {
    $this->assertContains(
      array('type' => 'number', 'value' => '42'),
      $this->lexer->run('42'.$whitespace)
    );
  }
  
  public function testIntegerNumberCanHaveAnExponent() {
    $this->assertContains(
      array('type' => 'number', 'value' => '42e1'),
      $this->lexer->run('42e1')
    );
  }
  
  public function testFloatNumberCanHaveAnExponent() {
    $this->assertContains(
      array('type' => 'number', 'value' => '42.1337e1'),
      $this->lexer->run('42.1337e1')
    );
  }
  
   public function testIntegerNumberCanHaveANegativeExponent() {
    $this->assertContains(
      array('type' => 'number', 'value' => '42e-1'),
      $this->lexer->run('42e-1')
    );
  }
  
  public function testFloatNumberCanHaveANegativeExponent() {
    $this->assertContains(
      array('type' => 'number', 'value' => '42.1337e-1'),
      $this->lexer->run('42.1337e-1')
    );
  }
  
  /*****************************************************************************
   * String
   ****************************************************************************/
  
  public function testStringCanBeSingleQuoted() {
    $this->assertContains(
      array('type' => 'string', 'value' => "'abc'"),
      $this->lexer->run("'abc'")
    );
  }
  
  public function testStringCanBeDoubleQuoted() {
    $this->assertContains(
      array('type' => 'string', 'value' => '"abc"'),
      $this->lexer->run('"abc"')
    );
  }
  
  public function testStringCanContainEscapedQuote() {
    $this->assertContains(
      array('type' => 'string', 'value' => "'a\'bc'"),
      $this->lexer->run("'a\'bc'")
    );
  }
  
  /*****************************************************************************
   * Quoted Identifier
   ****************************************************************************/
  
  public function testQuotedIdentifierIsBacktickQuoted() {
    $this->assertContains(
      array('type' => 'quoted_identifier', 'value' => "`abc`"),
      $this->lexer->run("`abc`")
    );
  }
  
  public function testQuotedIdentifierCannotContainEscapedBacktick() {
    $this->assertNotContains(
      array('type' => 'quoted_identifier', 'value' => "`ab\`c`"),
      $this->lexer->run("`ab\`")
    );
  }
  
  /*****************************************************************************
   * Logical Operator 
   ****************************************************************************/
  
  public function providerLogicalOperators() {
    $logicalOperators = array('and', '&&', 'or', '||', 'xor');
    
    $data = array();
    foreach ($logicalOperators as $logicalOperator) {
      $data[] = array($logicalOperator);
    }
    return $data;
  }
  
  /**
   * @dataProvider providerLogicalOperators
   */
  public function testMySqlLogicalOperatorIsLogicalOperator($logicalOperator) {
    $this->assertContains(
      array('type' => 'logical_operator', 'value' => $logicalOperator),
      $this->lexer->run($logicalOperator)
    );
  }
  
  public function testAmpAmpIsAnLogicalOperator() {
    $this->assertContains(
      array('type' => 'logical_operator', 'value' => "&&"),
      $this->lexer->run("&&")
    );
  }
  
  /**
   * @dataProvider providerQuoteCharacters
   */
  public function testLogicalOperatorCanBeFollowedByAQuote($quote) {
    $this->assertContains(
      array('type' => 'logical_operator', 'value' => "and"),
      $this->lexer->run("and" . $quote)
    );
  }
  
  /**
   * @dataProvider providerAlphaNumbericCharacters
   */
  public function testNonAlphaLogicalOperatorCanBeFollowedByAnAlphaNumbericCharacter($alphaNumChar) {
    $this->assertContains(
      array('type' => 'logical_operator', 'value' => "&&"),
      $this->lexer->run("&&" . $alphaNumChar)
    );
  }
  
  /**
   * @dataProvider providerAlphaNumbericCharacters
   */
  public function testAlphaLogicalOperatorCannotBeFollowedByAnAlphaNumbericCharacter($alphaNumChar) {
    $this->assertNotContains(
      array('type' => 'logical_operator', 'value' => "and"),
      $this->lexer->run("and" . $alphaNumChar)
    );
  }
  
  /*****************************************************************************
   * Operator 
   ****************************************************************************/
  
  public function providerOperators() {
    $operators = array('&&', '=', ':=', '&', '~', '|', '^', '/', '<=>', '>=', '>', '<<', '<=', '<', '-', '%', '...', '!=', '<>', '!', '||', '+', '>>', '*');
    
    // remove logical operators
    $operators = array_diff($operators, array('&&', '||'));
    
    $data = array();
    foreach ($operators as $operator) {
      $data[] = array($operator);
    }
    return $data;
  }
  
  /**
   * @dataProvider providerOperators
   */
  public function testMySqlOperatorIsAnOperator($operator) {
    $this->assertContains(
      array('type' => 'operator', 'value' => $operator),
      $this->lexer->run($operator)
    );
  }
  
  public function testCommentIsNotAMinusOperator() {
    $this->assertNotContains(
      array('type' => 'operator', 'value' => "-"),
      $this->lexer->run("-- foo")
    );
  }
  
  public function testCommentIsNotADivideOperator() {
    $this->assertNotContains(
      array('type' => 'operator', 'value' => "/"),
      $this->lexer->run("/* foo")
    );
  }
  
  // Remove this one, as this checks syntax?
  public function testOperatorCannotBeFollowedByAnOperator() {
    $this->assertNotContains(
      array('type' => 'operator', 'value' => "+*"),
      $this->lexer->run("+*")
    );
  }
  
  public function testOperatorCanBeFollowedByMultiLineComment() {
    $this->assertContains(
      array('type' => 'operator', 'value' => "/"),
      $this->lexer->run("//*")
    );
  }
  
  public function testOperatorCanBeFollowedBySingleLineComment() {
    $this->assertContains(
      array('type' => 'operator', 'value' => "-"),
      $this->lexer->run("---")
    );
  }
  
  /*****************************************************************************
   * Whitespace
   ****************************************************************************/
  
  /**
   * @dataProvider providerWhitespaceCharacters
   */
  function testMySqlValidWhitespaceIsWhitespace($whitespace) {
    $this->assertContains(
      array('type' => 'whitespace', 'value' => $whitespace),
      $this->lexer->run($whitespace)
    );
  }
  
  /*****************************************************************************
   * Identifier
   ****************************************************************************/
  
  public function providerKeywords() {
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
    
    // remove logical operators
    $keywords = array_diff($keywords, array('and', 'or', 'xor'));
    
    $data = array();
    foreach ($keywords as $keyword) {
      $data[] = array($keyword);
    }
    return $data;
  }
  
  /**
   * @dataProvider providerKeywords
   */
  function testMySqlKeywordIsKeyword($keyword) {
    $this->assertContains(
      array('type' => 'keyword', 'value' => $keyword),
      $this->lexer->run($keyword)
    );
  }
  
  public function providerFunctions() {
    $functions = array('concat', 'concat_ws', 'ascii', 'hex', 'unhex', 'sleep', 'md5', 'benchmark', 'not_regexp');
    
    $data = array();
    foreach ($functions as $function) {
      $data[] = array($function);
    }
    return $data;
  }
  
  /**
   * @dataProvider providerFunctions
   */
  function testPopularMysqlInjectionFunctionIsFunction($function) {
    $this->assertContains(
      array('type' => 'function', 'value' => $function),
      $this->lexer->run($function)
    );
  }
  
  public function providerModifiers() {
    $modifiers = array('boolean');
    
    $data = array();
    foreach ($modifiers as $modifier) {
      $data[] = array($modifier);
    }
    return $data;
  }
  
  /**
   * @dataProvider providerModifiers
   */
  function testPopularMysqlInjectionModifierIsModifier($modifier) {
    $this->assertContains(
      array('type' => 'modifier', 'value' => $modifier),
      $this->lexer->run($modifier)
    );
  }
  
  public function providerIdentifier() {
    return array(
      array('test'),
      array('test123'),
      array('test_123'),
      array('_test')
    );
  }
  
  /**
   * @dataProvider providerIdentifier
   */
  function testValidMySqlIdentifierIsIdentifier($identifier) {
    $this->assertContains(
      array('type' => 'identifier', 'value' => $identifier),
      $this->lexer->run($identifier)
    );
  }
  
  /**
   * @dataProvider providerNumericCharacters
   */
  function testIdentifierCanStartWithNumericCharacter($numChar) {
    $this->assertContains(
      array('type' => 'identifier', 'value' => $numChar.'test'),
      $this->lexer->run($numChar.'test')
    );
  }
  
  function testIdentifierCanContainDollarSign() {
    $this->assertContains(
      array('type' => 'identifier', 'value' => 'te$t'),
      $this->lexer->run('te$t')
    );
  }
  
  function testIdentifierCanContainDollarUnderscore() {
    $this->assertContains(
      array('type' => 'identifier', 'value' => 'te_st'),
      $this->lexer->run('te_st')
    );
  }
  
  /*****************************************************************************
   * Comment
   ****************************************************************************/
  
  function testPoundCommentEndsAtNewline() {
    $this->assertContains(
      array('type' => 'comment', 'value' => "#foo\n"),
      $this->lexer->run("#foo\nor 1=1")
    );
  }
  
  function testPoundCommentEndsAtEos() {
    $this->assertContains(
      array('type' => 'comment', 'value' => "#foo"),
      $this->lexer->run("#foo")
    );
  }
  
  function testMinusMinusCommentEndsAtNewline() {
    $this->assertContains(
      array('type' => 'comment', 'value' => "-- foo\n"),
      $this->lexer->run("-- foo\nor 1=1")
    );
  }
  
  function testMinusMinusCommentEndsAtImmediateNewline() {
    $this->assertContains(
      array('type' => 'comment', 'value' => "--\n"),
      $this->lexer->run("--\nfoo")
    );
  }
  
  function testMinusMinusCommentEndsAtEos() {
    $this->assertContains(
      array('type' => 'comment', 'value' => "-- foo"),
      $this->lexer->run("-- foo")
    );
  }
  
  /**
   * @dataProvider providerHorizontalWhitespaceCharacters
   */
  function testMinusMinusCommentCanBeFollowedByHorizontalWhitespace($whitespace) {
    $this->assertContains(
      array('type' => 'comment', 'value' => "--{$whitespace}foo"),
      $this->lexer->run("--{$whitespace}foo")
    );
  }
  
  function testUnconditionalCommentIsComment() {
    $this->assertContains(
      array('type' => 'comment', 'value' => "/* foobar */"),
      $this->lexer->run("/* foobar */")
    );
  }
  
  function testExecutedCommentContentIsParsed() {
    $this->assertEquals(array(
        array('type' => 'executed_comment_start', 'value' => "/*!"),
        array('type' => 'identifier', 'value' => "foobar"),
        array('type' => 'executed_comment_end', 'value' => "*/"),
      ),
      $this->lexer->run("/*!foobar*/")
    );
  }
  
  function testConditionalCommentContentIsParsed() {
    $this->assertEquals(array(
        array('type' => 'conditional_comment_start', 'value' => "/*!0"),
        array('type' => 'identifier', 'value' => "foobar"),
        array('type' => 'conditional_comment_end', 'value' => "*/"),
      ),
      $this->lexer->run("/*!0foobar*/")
    );
  }
  
  function testNextedConditionalAndExecutedCommentContentIsParsed() {
    $this->assertEquals(array(
        array('type' => 'conditional_comment_start', 'value' => "/*!0"),
        array('type' => 'executed_comment_start', 'value' => "/*!"),
        array('type' => 'identifier', 'value' => "foobar"),
        array('type' => 'executed_comment_end', 'value' => "*/"),
        array('type' => 'conditional_comment_end', 'value' => "*/"),
      ),
      $this->lexer->run("/*!0/*!foobar*/*/")
    );
  }
  
  /*****************************************************************************
   * Hexadecimal
   ****************************************************************************/
  
  function testXQuoteHexadecimalIsHexadecimal() {
    $this->assertContains(
      array('type' => 'hexadecimal', 'value' => "x'd0'"),
      $this->lexer->run("x'd0'")
    );
  }
  
  function testXQuoteHexadecimalCanConsistOfNoChars() {
    $this->assertContains(
      array('type' => 'hexadecimal', 'value' => "x''"),
      $this->lexer->run("x''")
    );
  }
  
  function testXQuoteHexadecimalCannotConsistOfOddNumberOfHexChars() {
    $this->assertNotContains(
      array('type' => 'hexadecimal', 'value' => "x'd'"),
      $this->lexer->run("x'd'")
    );
  }
  
  function test0XHexadecimalIsHexadecimal() {
    $this->assertContains(
      array('type' => 'hexadecimal', 'value' => "0xd0"),
      $this->lexer->run("0xd0")
    );
  }
  
  function test0XHexadecimalCannotConsistOfNoChars() {
    $this->assertNotContains(
      array('type' => 'hexadecimal', 'value' => "0x"),
      $this->lexer->run("0x")
    );
  }
  
  function test0XHexadecimalCanContainOddNumberOfHexChars() {
    $this->assertContains(
      array('type' => 'hexadecimal', 'value' => "x''"),
      $this->lexer->run("x''")
    );
  }
  
  function testHexadecimalCannotContainNonHexChar() {
    $this->assertNotContains(
      array('type' => 'hexadecimal', 'value' => "0xdz"),
      $this->lexer->run("0xdz")
    );
  }
  
  function testHexadecimalCannotBeFollowedByAlphaCharacter() {
    $this->assertNotContains(
      array('type' => 'hexadecimal', 'value' => "0xd0z"),
      $this->lexer->run("0xd0z")
    );
  }
  
  /*****************************************************************************
   * Bit-Field
   ****************************************************************************/
  
  function testBQuoteBitFieldIsBitField() {
    $this->assertContains(
      array('type' => 'bit_field', 'value' => "b'01'"),
      $this->lexer->run("b'01'")
    );
  }
  
  function testBitFieldCannotContainNonBinaryChar() {
    $this->assertNotContains(
      array('type' => 'bit_field', 'value' => "b'02'"),
      $this->lexer->run("b'02'")
    );
  }
  
  /*****************************************************************************
   * System Variables
   ****************************************************************************/
  
  function testMysqlSystemVariableIsSystemVariable() {
    $this->assertContains(
      array('type' => 'system_variable', 'value' => '@@version'),
      $this->lexer->run('@@version')
    );
  }
  
  function testSystemVariableCanContainDollarSign() {
    $this->assertContains(
      array('type' => 'system_variable', 'value' => '@@ver$ion'),
      $this->lexer->run('@@ver$ion')
    );
  }
  
  function testSystemVariableCanContainUnderscore() {
    $this->assertContains(
      array('type' => 'system_variable', 'value' => '@@ver_sion'),
      $this->lexer->run('@@ver_sion')
    );
  }
  
  function testSystemVariableCanContainPeriod() {
    $this->assertContains(
      array('type' => 'system_variable', 'value' => '@@ver.sion'),
      $this->lexer->run('@@ver.sion')
    );
  }
  function testSystemVariableCanContainDigit() {
    $this->assertContains(
      array('type' => 'system_variable', 'value' => '@@ver2ion'),
      $this->lexer->run('@@ver2ion')
    );
  }
  
  /*****************************************************************************
   * User-Defined Variables
   ****************************************************************************/
  
  function testUnquotedMysqlUserDefinedVariableIsUserDefinedVariable() {
    $this->assertContains(
      array('type' => 'user_defined_variable', 'value' => '@version'),
      $this->lexer->run('@version')
    );
  }
  
  function testUnquotedUserDefinedVariableCanContainDollarSign() {
    $this->assertContains(
      array('type' => 'user_defined_variable', 'value' => '@ver$ion'),
      $this->lexer->run('@ver$ion')
    );
  }
  
  function testUnquotedUserDefinedVariableCanContainUnderscore() {
    $this->assertContains(
      array('type' => 'user_defined_variable', 'value' => '@ver_sion'),
      $this->lexer->run('@ver_sion')
    );
  }
  
  function testUnquotedUserDefinedVariableCanContainPeriod() {
    $this->assertContains(
      array('type' => 'user_defined_variable', 'value' => '@ver.sion'),
      $this->lexer->run('@ver.sion')
    );
  }
  
  function testUnquotedUserDefinedVariableCanContainDigit() {
    $this->assertContains(
      array('type' => 'user_defined_variable', 'value' => '@ver2ion'),
      $this->lexer->run('@ver2ion')
    );
  }
  
  function testUserDefinedVariableCanBeSingleQuoted() {
    $this->assertContains(
      array('type' => 'user_defined_variable', 'value' => "@'version'"),
      $this->lexer->run("@'version'")
    );
  }
  
  function testUserDefinedVariableCanBeDoubleQuoted() {
    $this->assertContains(
      array('type' => 'user_defined_variable', 'value' => '@"version"'),
      $this->lexer->run('@"version"')
    );
  }
  
  function testUserDefinedVariableCanBeBacktickQuoted() {
    $this->assertContains(
      array('type' => 'user_defined_variable', 'value' => '@`version`'),
      $this->lexer->run('@`version`')
    );
  }
  
  /*****************************************************************************
   * Unknown
   ****************************************************************************/
  
  public function providerUnknowns() {
    $unknowns = array(
      ':foo',
      '[foo',
      '++',
    );
    
    $data = array();
    foreach ($unknowns as $unknown) {
      $data[] = array($unknown);
    }
    return $data;
  }
  
  /**
   * @dataProvider providerUnknowns
   */
  function testRemainingTokensAreUnknown($unknown) {
    $this->assertContains(
      array('type' => 'unknown', 'value' => $unknown),
      $this->lexer->run($unknown)
    );
  }
}