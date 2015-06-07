# Ultimo-WAF
Lexer based Web Application Firewall in PHP.
# IN DEVELOPMENT

Unlike other WAFs, Ultimo-WAF runs directly after the input parameters are collected, e.g. after routing. Each parameter is split into tokens using lexers for each type of attack. Then it tries to find patterns of token types to decide whether to advice to block the request.

## Goals
* Usable when more advanced WAFs, like ModSecurity or other commercial software, cannot be used. (too expensive, not enough privileges on the server, ...)
* Easy to setup
* Acceptable performance
* Withstand automated attacks from tools like sqlmap
* Hard challenge for casual hackers

## Requirements
* PHP 5.3

## Usage
TBD

## Todo
* FInd and test against a large set of mysql negatives
* Create fixtures of more mysql vulnerability tools like sqlmap and test against them: https://www.owasp.org/index.php/Testing_for_SQL_Injection_%28OTG-INPVAL-005%29#Tools
* Add vulnerability tool version and options/settings to fixture file, so fixtures can be regenerated
* Refactor code, much of the Lexer, TokenMatcher and Tester can be reused for other type of attacks
* Add phpdoc
* Create generic extensible WAF core
* Create more extensions (besides mysql) for the WAF core for vulnerabilities in OWASP top 10
* Create plugins/extensions for frameworks so they can make use of ultimo-waf easily
* Find a better name for the Tester
* It this a WAF, IDS, IPS or something else? 