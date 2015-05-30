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