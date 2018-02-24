# tidy
A set of scripts to tidy (pretty print) source code according to various standards.

Each of the tidy scripts has three modes of operation.
1. With no arguments, takes input from stdin and writes it to stdout. This is useful inside vim. If
you select the lines, such as SQL lines, then run the tidy script on them, output will replace
input.
2. With a file argument, writes a .bak file, then tidies the original file in place.
3. With a directory argument, iterates over all the files with the right extensions in a directory
then tidies each one as in 2.

Each script can take a list of files or directories, and not just a single operand.

These programs rely on complex formatting and regular expressions, and a lot can go wrong. Be sure
to proof the output before committing.

## Installation

1. `git clone https://github.com/DouglasGreen/tidy.git`
2. `cd tidy`
3. `composer install`
4. `npm install js-beautify`
5. Add to path in Bash profile: `PATH=$PATH:$HOME/tidy`

## Usage

### css_tidy

css_tidy uses https://github.com/Cerdic/CSSTidy to sort properties and selectors.

Then it uses https://github.com/sabberworm/PHP-CSS-Parser to prettify with a 4-space indent.

Comments are allowed only outside CSS rules.

### html_tidy

html_tidy uses the built-in http://php.net/manual/en/book.tidy.php function, then does a bunch of
cleanup.

If there are any new tags it doesn't recognize, just add them to new-blocklevel-tags in the source.

### js_tidy

js_tidy uses js-beautify (https://github.com/beautify-web/js-beautify) with a JS Beautifier
configuration for Airbnb (https://gist.github.com/softwarespot/8a6d9bb6b848f3cc9824). The exception
to Airbnb standards is that it uses a 4-space indent, like all these other scripts.

### php_tidy

php_tidy uses a combination of https://github.com/FriendsOfPHP/PHP-CS-Fixer and
https://github.com/squizlabs/PHP_CodeSniffer/wiki/Fixing-Errors-Automatically.

### sql_tidy

sql_tidy uses https://github.com/phpmyadmin/sql-parser. This is the most mature SQL parser I found
and is the one used by phpMyAdmin.

### sweepdir

Change to the directory that is littered with leftover tidy files, like .bak and .php_cs.cache. Then
run this script to get rid of them.
