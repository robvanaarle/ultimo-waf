<?php

namespace ultimo\waf;

class Core {
	
	/**
	 * Adds multiple rules.
	 * @param array $rules Array with rules.
	 * @return Core This instance for fluid design.
	 */
	public function addRules(array $rules) {
		return $this;
	}
	
	/**
	 * Adds a single rules. A rule is a hashtable with the following keys:
	 *  id: id of the rule
	 *  wl: id of the rule to whitelist
	 *  mz: match zone
	 *  msg: human readable message
	 *  s: score section
	 *  negative: boolean indicating whether the rule is a negative
	 * @param array $rules Array with rules.
	 * @return Core This instance for fluid design.
	 */
	public function addRule(array $rule) {
		return $this;
	}
	
	/**
	 * Checks (parts of) a HTTP request against the rules and return the scores
	 * for each score section and it's matching rules.
	 * @param array $request Hashtable with HTTP request parts:
	 *   url: URL without host, e.g. /foo?bar=1 in http://www.server.com/foo?bar=1
	 *   headers: hashtable with header names as keys and header values as values
	 *   params: hashtable with param names as keys and param values as values
	 * None of the parts are required, this way this function can be called with
	 * parameters after routing.
	 * @return array Result of the check as a hashtable with score section names
	 * as key and a hashtable with the following keys as value:
	 *  score: score of the section
	 *  rules: array with the matching rules
	 */
	public function check(array $request) {
		return array();
	}
}