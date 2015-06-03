<?php

namespace ultimo;

class UrlencodedQueriesFileIterator implements \Iterator {
    protected $file;
    protected $key = 0;
    protected $current;
    protected $urlencoded;

    public function __construct($file, $urlencoded=false) {
      $this->file = fopen($file, 'r');
      $this->urlencoded = $urlencoded;
    }

    public function __destruct() {
      fclose($this->file);
    }

    public function rewind() {
      rewind($this->file);
      $this->key = -1;  
      $this->next();
    }

    public function valid() {
      return !feof($this->file);
    }

    public function key() {
      return $this->key;
    }

    public function current() {
      return $this->current;
    }

    public function next() {
      $value = rtrim(fgets($this->file), "\r\n");
      if ($this->urlencoded) {
        $value = urldecode($value);
      }
      $this->current = array($value);
      $this->key++;
    }
}