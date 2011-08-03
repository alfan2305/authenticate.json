# authenticate.json README

`authenticate.json` is a JSON/JSONP service for MIT certificate authentication. Use it to grab basic user information via MIT certificates on sites which are not setup with Shibboleth/Touchstone.

It has only been tested on scripts.mit.edu. Not for use with sensitive information.

by [mitcho](http://mitcho.com), `mitcho@mit.edu`, August 1, 2011

## Setup

Put on a server such as scripts which has `SSL_Client` enabled for use with MIT certificates.

If you're on scripts, make sure you have a php.ini that looks like:

	magic_quotes_gpc = no
	extension = ldap.so
	extension = json.so

and has the following in .htaccess:

	RewriteEngine on
	RewriteRule ^authenticate.json$ %{REQUEST_URI}.php

## Usage:

Include the script `https://scripts.mit.edu:444/~locker/authenticate.json` with the parameter `?callback=...` to set a JSONP wrapper function. Or, with [jQuery](http://jquery.com), call `$.getJSON('authenticate.json?callback=?')` and you'll get the object returned to you.

The return value is an object. If certificate authentication failed, return object will have property authenticated = false. Else, authenticated will be true and there will be a number of other properties of the MIT user returned from LDAP + finger.
