# Session Allow for ProcessWire

Enables you to configure whether to allow session for each request based on configured rules.
Requires ProcessWire 3.0.184 or newer. 

## Introduction

It takes some overhead to maintain a session on every web request, whether you are getting
and setting session data or not. Being able to limit use of sessions can result in faster 
performance. This module lets you to specifically enable or disable sessions according to 
configured rules. This lets you focus your resources for sessions to just the requests 
where they will be needed. 

You can already accomplish this with the ProcessWire `$config->sessionAllow` setting and
a dynamic function, but it can be somewhat challenging to use because it requires your 
own custom code, and it is called before any of ProcessWire’s API is available. This module
provides a much easier route for control over when sessions are started. 

## How to install

1. Copy the files for this module to: `/site/modules/SessionAllow/`
   
2. In your admin go to *Modules > Refresh*.    

3. Click “Install” for *Session Allow*. You should now be on the module configuration screen. 
   See the next section for more details 
   
## Configuration   

### Allow sessions by default?

**When sessions are enabled by default**, all requests are assumed to use sessions unless they
match rules you configure:

  - **Disallow session when request URL matches:**  
    URLs or URL-matching rules that will prevent sessions from being started when they match 
    the current request. 
    
  - **Disallow sessions for checked hosts:**  
    Hosts where you would like login sessions to be disabled. 
    
    *If you don’t see a host you expect, make sure it is present in your `$config->httpHosts` 
    setting in /site/config.php.*

**When sessions are NOT enabled by default***, all requests are assumed NOT to use sessions,
unless they match the rules you configure. In this case, you will be configuring the
following:

  - **Allow session when request URL matches:**   
    URLs or URL-matching rules that will enable sessions when they match the current request. 
    
  - **Allow sessions for checked hosts:**  
    Hosts where you would like login sessions to always be enabled. 

### Session always active when login cookie present?

This setting bypasses any other rules you might configure in that if it detects the 
presence of a login cookie, the session will always be allowed. This ensures that once
a user is logged in, it will be known by all pages the user visits in your site. Note that
sometimes a login cookie remains present after a user’s login session has expired. This 
is not a probem, but just something to be aware of. 

### Matching request URLs

The “Allow/Disallow session when request URL matches” settings accept one rule per line.
Each rule can be any of following:

- A page path, i.e. `/path/to/page/` for performing an exact match.

- A page path with wildcards for performing a partial match. For example `/foo/bar/*` 
  matches everything starting with /foo/bar/ while `*/foo/bar/` matches everything ending 
  with /foo/bar/, and `*/foo/bar/*` matches any page path that contains /foo/bar/ anywhere 
  within it.
  
- **For advanced users:** A PCRE regular expression that matches a page path. When using 
  this, you may use any of these characters as your starting/ending delimiters: 
  `!`, `#`, `@`, `%`. For example the expression `!^/foo/(bar|baz)/?$!` would match 
  /foo/bar/ and /foo/baz/, and it would match whether the trailing slash was present or not. 
  
Your rules are matching the page PATH rather than the URL. Meaning, if your site is 
running off a subdirectory, you should not include the subdirectory in your matching rules. 
If your site is not running from a subdirectory, then the PATH and URL are the same thing.

Note that page paths always start with a slash `/`, and may or may not have a trailing 
slash, depending on what your site is configured to support. If you want to make a trailing
slash optional in your rules, append a `?` to the trailing slash, i.e. `/?` and this will 
work whether using a path, wildcard or regular expression match.

### Test rules

The module configuration screen provides a field where you can enter one or more page
paths or hostnames and test your configured rules. Note that it identifies paths as lines
that start with a slash `/` and it identifies hosts as lines that do not start with a slash.

## Planned additions

I would like for this module to support configuration by template, enabling you to configure
whether sessions are allowed or not according to the page template that is in use. Currently
this is not possible because ProcessWire initiates sessions before it determines what the 
current Page is. My plan is to make some changes to the core in order to support an option
for identifying the current page before starting the session. Once this change is in place,
I will also update this module to support enable/disable of sessions by template.

---
Copyright 2021 by Ryan Cramer


