<?php

namespace ultimo\waf\mysql;

class MySqlLexerTest extends \PHPUnit_Framework_TestCase {
  protected $lexer;
  
  protected function setUp() {
    $this->lexer = new MySqlLexer();
  }
  
  public function providerAlphaNumericCharacters() {
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
  
  public function providerIdentifier() {
    return array(
      array('test'),
      array('test123'),
      array('test_123'),
      array('te$t'),
      array('0test'),
      array('_test'),
      array('$'),
      array('0$'),
      array('_')
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
  
  /*****************************************************************************
   * Custom types
   ****************************************************************************/
  
  function testCustomTypeCanHaveAlphaNumericValue() {
    $lexer = new MySqlLexer(array('keyword' => array('select')));
    $this->assertContains(
      array('type' => 'keyword', 'value' => "select"),
      $lexer->run("select")
    );
  }
  
  function testCustomTypeCanHaveNonAlphaNumericValue() {
    $lexer = new MySqlLexer(array('comparison-operator' => array('<=>')));
    $this->assertContains(
      array('type' => 'comparison-operator', 'value' => "<=>"),
      $lexer->run("<=>")
    );
  }
  
  /**
   * @dataProvider providerAlphaNumericCharacters
   */
  function testCustomTypeWithNonAlphaNumericValueCanBeFollowedByAlphaNumericCharacter($alphaNumChar) {
    $lexer = new MySqlLexer(array('comparison-operator' => array('<=>')));
    $this->assertContains(
      array('type' => 'comparison-operator', 'value' => "<=>"),
      $lexer->run("<=>".$alphaNumChar)
    );
  }
  
  public function testMinMinCommentIsNotACustomType() {
    $lexer = new MySqlLexer(array('math-operator' => array('-')));
    $this->assertNotContains(
      array('type' => 'math-operator', 'value' => "-"),
      $lexer->run("-- foo")
    );
  }
  
  public function testCommentIsNotACustomType() {
    $lexer = new MySqlLexer(array('math-operator' => array('/')));
    $this->assertNotContains(
      array('type' => 'math-operator', 'value' => "/"),
      $lexer->run("/* foo */")
    );
  }
  
  public function testCustomTypeWithNonAlphaNumericValueCanBeFollowedByMinMinComment() {
    $lexer = new MySqlLexer(array('math-operator' => array('/')));
    $this->assertContains(
      array('type' => 'math-operator', 'value' => "/"),
      $lexer->run("/-- foo")
    );
  }
  
  public function testCustomTypeWithNonAlphaNumericValueCanBeFollowedByPoundComment() {
    $lexer = new MySqlLexer(array('math-operator' => array('/')));
    $this->assertContains(
      array('type' => 'math-operator', 'value' => "/"),
      $lexer->run("/#foo")
    );
  }
  
  public function testCustomTypeWithNonAlphaNumericValueCanBeFollowedByMultiLineComment() {
    $lexer = new MySqlLexer(array('math-operator' => array('/')));
    $this->assertContains(
      array('type' => 'math-operator', 'value' => "/"),
      $lexer->run("//*")
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
        array('type' => 'executed-comment-start', 'value' => "/*!"),
        array('type' => 'identifier', 'value' => "foobar"),
        array('type' => 'comment-end', 'value' => "*/"),
      ),
      $this->lexer->run("/*!foobar*/")
    );
  }
  
  function testConditionalCommentContentIsParsed() {
    $this->assertEquals(array(
        array('type' => 'conditional-comment-start', 'value' => "/*!0"),
        array('type' => 'identifier', 'value' => "foobar"),
        array('type' => 'comment-end', 'value' => "*/"),
      ),
      $this->lexer->run("/*!0foobar*/")
    );
  }
  
  function testNextedConditionalAndExecutedCommentContentIsParsed() {
    $this->assertEquals(array(
        array('type' => 'conditional-comment-start', 'value' => "/*!0"),
        array('type' => 'executed-comment-start', 'value' => "/*!"),
        array('type' => 'identifier', 'value' => "foobar"),
        array('type' => 'comment-end', 'value' => "*/")
      ),
      $this->lexer->run("/*!0/*!foobar*/")
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
      array('type' => 'bit-field', 'value' => "b'01'"),
      $this->lexer->run("b'01'")
    );
  }
  
  function testBitFieldCannotContainNonBinaryChar() {
    $this->assertNotContains(
      array('type' => 'bit-field', 'value' => "b'02'"),
      $this->lexer->run("b'02'")
    );
  }
  
  /*****************************************************************************
   * System Variables
   ****************************************************************************/
  
  function testMysqlSystemVariableIsSystemVariable() {
    $this->assertContains(
      array('type' => 'system-variable', 'value' => '@@version'),
      $this->lexer->run('@@version')
    );
  }
  
  function testSystemVariableCanContainDollarSign() {
    $this->assertContains(
      array('type' => 'system-variable', 'value' => '@@ver$ion'),
      $this->lexer->run('@@ver$ion')
    );
  }
  
  function testSystemVariableCanContainUnderscore() {
    $this->assertContains(
      array('type' => 'system-variable', 'value' => '@@ver_sion'),
      $this->lexer->run('@@ver_sion')
    );
  }
  
  function testSystemVariableCanContainPeriod() {
    $this->assertContains(
      array('type' => 'system-variable', 'value' => '@@ver.sion'),
      $this->lexer->run('@@ver.sion')
    );
  }
  function testSystemVariableCanContainDigit() {
    $this->assertContains(
      array('type' => 'system-variable', 'value' => '@@ver2ion'),
      $this->lexer->run('@@ver2ion')
    );
  }
  
  /*****************************************************************************
   * User-Defined Variables
   ****************************************************************************/
  
  function testUnquotedMysqlUserDefinedVariableIsUserDefinedVariable() {
    $this->assertContains(
      array('type' => 'user-defined-variable', 'value' => '@version'),
      $this->lexer->run('@version')
    );
  }
  
  function testUnquotedUserDefinedVariableCanContainDollarSign() {
    $this->assertContains(
      array('type' => 'user-defined-variable', 'value' => '@ver$ion'),
      $this->lexer->run('@ver$ion')
    );
  }
  
  function testUnquotedUserDefinedVariableCanContainUnderscore() {
    $this->assertContains(
      array('type' => 'user-defined-variable', 'value' => '@ver_sion'),
      $this->lexer->run('@ver_sion')
    );
  }
  
  function testUnquotedUserDefinedVariableCanContainPeriod() {
    $this->assertContains(
      array('type' => 'user-defined-variable', 'value' => '@ver.sion'),
      $this->lexer->run('@ver.sion')
    );
  }
  
  function testUnquotedUserDefinedVariableCanContainDigit() {
    $this->assertContains(
      array('type' => 'user-defined-variable', 'value' => '@ver2ion'),
      $this->lexer->run('@ver2ion')
    );
  }
  
  function testUserDefinedVariableCanBeSingleQuoted() {
    $this->assertContains(
      array('type' => 'user-defined-variable', 'value' => "@'version'"),
      $this->lexer->run("@'version'")
    );
  }
  
  function testUserDefinedVariableCanBeDoubleQuoted() {
    $this->assertContains(
      array('type' => 'user-defined-variable', 'value' => '@"version"'),
      $this->lexer->run('@"version"')
    );
  }
  
  function testUserDefinedVariableCanBeBacktickQuoted() {
    $this->assertContains(
      array('type' => 'user-defined-variable', 'value' => '@`version`'),
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