=== DGE_InlineRSS ===
Tags: xsl, xslt, rss, feed, feeds, atom, rdf, html, parsing
Contributors: delcock, Cal Demaine
Requires at least: 2.0?
Tested up to: 2.2
Stable tag: 0.93

Allows inlcusion of RSS feeds, external html etc from any source in any format, and optionally transforms the feed via XSLT.

== Description ==

== Installation ==

1. Download the plugin and unzip it into to your wp-content/plugins folder.
3. Set up the cache directory (see below)
4. Go to your wordpress admin page, and activate the plugin.
5. Configure options in the options page from wordpress admin (Options
   >> InlineRSS).

#### Cache directory

The plugin requires a cache directory somewhere below the wordpress
installation folder, with world read/write acces (mode 777). Point the
plugin at it via the options page within WP admin panel, using a
relative path from the wordpress folder. So, for example, my options
page has a 'Cache path' setting of 'wp-content/cache'. You might
consider giving this a random name for security purposes, like
'wp-content/cache-n38Q'. Up to you really.

#### Web server

The [XSLT](http://uk2.php.net/xslt) functions used require some form
of PHP XSL extension. PHP 5's XSL extension is what I've been
exclusively testing with. According to the PHP manual, PHP 5 includes
the XSL extension by default. Some old and probably broken PHP 4
fallbacks are still in this module's code, but it has remained
untested for over a year. I have no way to test it. I'm considering
just flat-out saying 'PHP 5 required'.

There's also use of [CURL](http://uk2.php.net/manual/en/ref.curl.php),
so I'd advise enabling this PHP extension too. The fallback to
[file\_get\_contents](http://uk2.php.net/manual/en/function.file-get-contents.php)
is also untested, but is so simple it should work. :)


== Usage ==

First of all, you need to set up a preset in the options page. Each
preset has:

* Name: a unique name for referencing the feed from your php or posts
* URL: where to fetch the feed from
* Options: optional arguments to affect output
* XSLT parameters: optional parameters to feed to any XSL
  transformations.

The most obvious application of this is to pull in a news feed and
transform it nicely for placing on your site.

You can access the functionality via two methods, either by calling
the `DGE_InlineRSS` method directly, or by using the inline filter
method.

#### Filter method

This is the simplest method, and can be called from anywhere in a post or page without editing any PHP.

Just write `!inlineRSS:` followed by a preset name. So for example if you'd set one up for the latest BBC news with a name of 'bbcnews', you'd write this anywhere in your post:

	!inlineRSS:bbcnews

#### Direct method

Still keeping it simple, you can call the `DGE_InlineRSS` function
directly from your theme templates, just `echo`ing the result:

	echo DGE_InlineRSS('uniquename');

However, it can get quite complicated if you want to. You can call it
from plugin code, supplying all the details without ever actually
setting up a preset.

	$xml = DGE_InlineRSS('davesplugin', $url,
	                     // InlineRSS options in the first array
	                     array('timeout'=>0,
	                           'xslt'=>'davesplugin.xsl'),
	                     // Your own options for the XSLT parameters
	                     array('limit'=>10));

== Options ==

**timeout**=_`<value>`_

* The time in minutes to cache the results for

**xslt**=_`<filename>`_

* An xsl file to use for translating the feed

**xml**=_`<string>`_

* Pass in the xml you want translated, bypassing the url.

**html** _(on it's own)_

* Tells the xslt processor to treat the input as html, rather than xml.


== History ==

This is a fork, and subsequent development, of the Cal Demaine's
[inlineRSS](http://www.iconophobia.com/wordpress/?page_id=55) plugin
(v1.1). Cal put a lot of work into his plugin, and I'm very grateful
for those foundations. However, I've decided to fork from v1.1 to meet
my own needs. So far, I added some handy features and bug fixes. Cal,
if you're listening/watching, feel free to incorporate any of this
into your plugin.

Details of the initial changes, plus historical necessity for them are
[here](http://dave.coolhandmook.com/2006/07/13/scratch-that-xslt-is-the-way-forward/).

[Full changelog here](http://dev.wp-plugins.org/log/dge-inlinerss/)

#### 0.9

* Options panel in wordpress admin area
* Config file replaced by 'presets' in options page
* Configurable cache dir, cache file prefix, and xslt dir.
* Simplified php call down to an id, url, array of options, array of
  xslt params.
* Unix-like path searching for xslt files.

#### 0.1

All original [inlineRSS](http://www.iconophobia.com/wordpress/?page_id=55) features, plus:

* Allows passing of parameters into XSL template.
* PHP 5 fixes.

== Related plugins ==

* [DGE_SlideShow](http://dev.wp-plugins.org/wiki/dge-slideshow) uses
  this plugin to fetch photo streams from Zooomr or Flickr.

== Copyright ==

Copyright (C) 2005 Cal Demaine (original work)

Copyright (C) 2006 Dave Elcock (further developments)

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

The GNU General Public License is available here:
  http://www.gnu.org/copyleft/gpl.html
