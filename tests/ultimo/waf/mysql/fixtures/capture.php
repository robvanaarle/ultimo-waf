<?php
/*
Script to capture injections by tools like sqlmap to create data sets to test against.

The captured injetions are passed to the function capture(), were they are stored in
a table with the following structure:

CREATE TABLE IF NOT EXISTS `captured_injection` (
  `id` int(11) NOT NULL auto_increment,
  `created_at` datetime NOT NULL,
  `injection` text NOT NULL,
  `tool` varchar(255) NOT NULL,
  `options` varchar(255) NOT NULL,
  `verdict` varchar(16) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

*/

$db = new mysqli('host', 'username', 'password', 'database_name');

if ($mysqli->connect_error) {
  die($mysqli->connect_error);
}


$defaultOptions = array(
  'quote' => 'no',
  'print_error' => 1,
  'print_result' => 1,
  'multi_query' => 0,
  'waf' => 0,
  'tool' => 'debug',
  'debug_print_query' => 0
);

function capture($injection, $options, $verdict) {
  global $db;
  $tool = $options['tool'];
  $optionKeys = array('quote', 'print_error', 'print_result', 'multi_query', 'waf');
  ksort($options);
  $opts = array();
  foreach ($options as $name => $value) {
    if (!in_array($name, $optionKeys)) {
      continue;
    }
    $opts[] = "{$name}={$value}";
  }
  $opts = implode('&', $opts);

  $query = "INSERT INTO `captured_injection` VALUES(0, '" . date("Y-m-d H:i:s") . 
    "' ,'" . $db->escape_string($injection) . "', '" . $db->escape_string($tool) . "', '" . $db->escape_string($opts) . "', '" . $db->escape_string($verdict) . "')";
  $db->query($query);
}

?>
<html>
  <head>
    <title>MySQL injection capturer</title>
  </head>
  <body>
    <h3>MySQL injection capturer</h3>
    Captures MySQL injection attacks by tools like Sqlmap. This script accepts exacly one GET parameter.
    Options are encoded in the name of the variable to prevent a tool attempting injection on option
    parameters. Options are encoded in the name of the parameter as <i>name\-\-value</i>, options are
    delimited by '-'. Example:<br />
     ?quote:no-print_error:1-print_result:1-tool:sqlmap-multi_query:0=42<br />
    <br />
    Options:<br />
    - quote = [no | sq | dq], default: no<br />
    - print_error = [0 | 1], default: 1<br />
    - print_result = [0 | 1], default: 1<br />
    - tool = <i>string</i>, default 'debug'<br />
    - multi_query = [0 | 1], default: 0<br />
    - waf = [0 | 1], default: 0<br />
    - debug_print_query = [0 | 1] (debug only), default: 0<br /><br />
    Different combinations of options can trigger other types of injection attempts by the scan/attack tool. Play
    around with the options to enhance the data set.
<?php

if (count($_GET) != 1) {
  die ("Provide exactly one GET paramater");
}

// get the name and value of the GET parameter
$getKeys = array_keys($_GET);
$name = $getKeys[0];
$value = $_GET[$name];

// parse the name of the GET parameter to get the options
$options = array();
if (preg_match_all("/([a-z\_]+)\:([a-z0-9]+)/", $name, $matches)) {
  $options = array();
  foreach($matches[1] as $i => $optionName) {
    $options[$optionName] = $matches[2][$i];
  }
}
$options = array_merge($defaultOptions, $options);

// construct query
$quotes = array(
  'no' => '',
  'sq' => "'",
  'dq' => '"'
);
$quote = $quotes[$options['quote']];

$query = 
"SELECT *
FROM `employees`
WHERE id = {$quote}{$value}{$quote}";

if ($options['debug_print_query']) {
  echo "<pre>";
  echo $query;
  echo "</pre>";
}

$verdict = '';
if ($options['waf']) {
  require_once('MySqlLexer.php');
  require_once('MySqlTester.php');
  require_once('MySqlTokenMatcher.php');

  $config = json_decode(file_get_contents('mysql.json'), true);
  $thresholdScore = $config['threshold-score'];
  $tester = new \ultimo\waf\mysql\MySqlTester($config);

  $result = $tester->test($value);

  if ($result['score'] > $thresholdScore) {
    $verdict = 'block';
  } else {
    $verdict = 'pass';
  }
}

capture($value, $options, $verdict);

if ($verdict != 'block') {
  if ($options['multi_query']) {
    $queryResult = $db->multi_query($query);  
  } else {
    $queryResult = $db->query($query);
  }

  if ($options['print_result']) {
    echo "<pre>";
    if ($queryResult) {
      if ($options['multi_query']) {
        do {
          $rows = array();
          if ($result = $db->store_result()) {
            while ($row = $result->fetch_assoc()) {
              $rows[] = $row;
            }
            $result->free();
          }
          print_r($rows);

          if ($db->more_results()) {
            echo "<hr>";
          }
        } while ($db->next_result());
      } else {
        $rows = array();
        while ($row = $queryResult->fetch_assoc()) {
          $rows[] = $row;
        }
        $queryResult->free();
        print_r($rows);
      }
    }
    echo "</pre>";
  }

  if ($db->error && $options['print_error']) {
    die($db->error);
  }
}

$db->close();

?>
  </body>
</html>