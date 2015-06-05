<?php

namespace ultimo\waf\mysql;

class MySqlDefaultRulesTest extends \PHPUnit_Framework_TestCase {
  
  protected $tester = null;
  protected $thresholdScore;
  
  public function setUp() {
    if ($this->tester === null) {
      $config = json_decode(file_get_contents(__DIR__ . '/../../../../config/mysql.json'), true);
      $this->thresholdScore = $config['threshold-score'];
      $this->tester = new MySqlTester($config);
    }
  } 
  
  public function getUrlencodedQueriesFileIterator($filename, $urlencoded=false) {
    return new \ultimo\UrlencodedQueriesFileIterator(__DIR__ . '/fixtures/' . $filename, $urlencoded);
  }
  
  public function providerSmallSetPositives() {
    return $this->getUrlencodedQueriesFileIterator('small_set_positives.txt');
  }
  
  /**
   * @dataProvider providerSmallSetPositives
   */
  public function testSmallSetPositives($value) {
    $result = $this->tester->test($value);
    $this->assertGreaterThanOrEqual($this->thresholdScore, $result['score'], "Input tested negative for injection. Result: " . print_r($result, true));
  }
  
  public function providerAntiUltimoWafPositives() {
    return $this->getUrlencodedQueriesFileIterator('anti-ultimo-waf_positives.txt');
  }
  
  /**
   * @dataProvider providerAntiUltimoWafPositives
   */
  public function testAntiUltimoWafPositives($value) {
    $result = $this->tester->test($value);
    $this->assertGreaterThanOrEqual($this->thresholdScore, $result['score'], "Input tested negative for injection. Result: " . print_r($result, true));
  }
  
  public function providerModSecurityChallengePositives() {
    return $this->getUrlencodedQueriesFileIterator('modsecurity-challenge_positives.txt', true);
  }
  
  /**
   * @dataProvider providerModSecurityChallengePositives
   */
  public function testAntiModSecurityChallengePositives($value) {
    // https://www.trustwave.com/Resources/SpiderLabs-Blog/ModSecurity-SQL-Injection-Challenge--Lessons-Learned/
    $result = $this->tester->test($value);
    $this->assertGreaterThanOrEqual($this->thresholdScore, $result['score'], "Input tested negative for injection. Result: " . print_r($result, true));
  }
  
  public function providerAssortedPositives() {
    return $this->getUrlencodedQueriesFileIterator('assorted_positives.txt');
  }
  
  /**
   * @dataProvider providerAssortedPositives
   */
  public function testAssortedPositives($value) {
    $result = $this->tester->test($value);
    $this->assertGreaterThanOrEqual($this->thresholdScore, $result['score'], "Input tested negative for injection. Result: " . print_r($result, true));
  }
  
  public function providerAssortedNegatives() {
    return $this->getUrlencodedQueriesFileIterator('assorted_negatives.txt');
  }
  
  /**
   * @dataProvider providerAssortedNegatives
   */
  public function testAssortedNegatives($value) {
    $result = $this->tester->test($value);
    $this->assertLessThan($this->thresholdScore, $result['score'], "Rule tested positive for normal input. Result: " . print_r($result, true));
  }
  
  public function providerSqlmapPositives() {
    return $this->getUrlencodedQueriesFileIterator('sqlmap_positives.txt', true);
  }
  
  /**
   * @dataProvider providerSqlmapPositives
   */
  /*public function testSqlmapPositives($value) {
    ini_set("memory_limit","768M");
    $result = $this->tester->test($value);
    $this->assertGreaterThanOrEqual($this->thresholdScore, $result['score'], "Input tested negative for injection. Result: " . print_r($result, true));
  }/*
  
  /*public function testPerformance() {
    $start = microtime(true);

    for ($i=0; $i<100; $i++) {
      $value = "2' IN BOOLEAN MODE) UNION ALL SELECT NULL#'";
      $result = $this->tester->test($value);
    }

    $end = microtime(true);

    $time = $end - $start;
    $avg = $time/100;
    $time = round($time, 3);
    $avg = round($avg, 3);
    echo "Time: {$time}s, avg: {$avg}";
    
  }*/

  /**
   * 
   */
  public function testManual() {
    //$value = "('2);(SELECT * FROM (SELECT(SLEEP(5)))sLoH)#')";
    $value = "2' IN BOOLEAN MODE) UNION ALL SELECT NULL#'";
    $result = $this->tester->test($value);
    $this->assertGreaterThanOrEqual($this->thresholdScore, $result['score'], "Input tested negative for injection. Result: " . print_r($result, true));
  }
 
}