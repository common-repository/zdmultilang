=== ZdMultiLang ===
Contributors: ZenDreams, PauSanchez
Donate link: http://blog.zen-dreams.com/en/wordpress/zdmultilang/#donate
Tags: Multi language, Wordpress, Zen-Dreams
Requires at least: 2.5.0
Tested up to: 2.8.6
Stable tag: 1.2.5

ZdMultiLang is a multilingual plugin for wordpress

== Description ==

ZdMultiLang is a wordpress plugin allowing you to blog in multiple languages.

Here is a list of functions :

* Translate posts ang pages
* Translate categories and tags
* Switch blog language
* Widget to change currently viewed language

== Changelog ==

v1.2.5:

* The most expected feature is now working. You can have static pages as a frontpage !
* Updated a bug with translation icon in the page while the page has never been saved
* Added options to hide the flag in the widget
* function zd_multilang_menu now takes two paramaters : zd_multilang_menu (show_language_name, show_language_flag) by default these are true
* Added an option to display original post while translating
* Added a donate button if you want to support development of the plugin
* Added an option to select who can translate things
* Added an option to keep comments separated - work by <a href="http://www.codigomanso.com/">Pau Sanchez</a>
* Added an autosave feature, can be enabled/disabled from the option page. It will autosave every 5 minutes unless the status is published

v1.2.4:

* Update for Wordpress 2.8 : Media Upload fix + Save button fix

v1.2.1:

* Updated a variable used to make translations, a bug prevented to translate posts/pages. This has been corrected.

v1.2.0:

* Completely redesigned the editor page
* Added import from google feature
* Won't display flags for current language
* Added draft feature for translations
* Added term descriptions
* Added link descriptions and translations, Blog name, Blog description
* Better definition of Languages
* Compatible with Wordpress 2.7

v1.1.1:

* Added the media bar in the html editor
* Found the reason why the editor didn't work : csforms2 is breaking the tiny_mce editor, to fix it, just disable the WP Editor Button support in your general settings.

v1.1.0:

* Added the possibility to switch default languages (meaning: exchange original posts/tags/cats with translated ones)
* Added translate links directly into the manage pages & into the Media zone of page/posts edition
* Added an option to generate permalinks for default language (ex-default behavior was always yes)
* Changed default character set as UTF-8
* Added an option to add "Translate with Google Translate" to untranslated posts
* Added an option to hide untranslated posts if they exists (ex-default : show all posts)

v1.0.1:

* Updated all database queries, reduced the numbers of queries executed per page (about 200 queries less) via caching methods. The plugin should now require something like 9 queries per page (which is huge i Know, but to translate a blog, this is "necessary").

== Installation ==

1. Setup is very simple, just unzip the archive in your wp-content/plugins folder
2. You can now activate the plugin and change the options.
3. Setup languages and start to translate stuffs

More on [Official Zdmultilang Page](http://blog.zen-dreams.com/en/zdmultilang "Official Zdmultilang Page")

== Frequently Asked Questions ==
See [Official FAQ](http://blog.zen-dreams.com/en/zdmultilang "Official FAQ")

= I would like to have separated comments for each language. =
In order to have comments tracked separatly for each language, you will have to change your comments.php template file like this:
From this:

action="&lt;?php echo get_option('siteurl'); ?&gt;/wp-comments-post.php"

to this:

action="&lt;?php echo get\_option('siteurl'); ?&gt;/wp-comments-post.php?lang=&lt;?php echo zd_multilang\_get\_locale(); ?&gt;"



== Screenshots ==
You can check [this page](http://blog.zen-dreams.com/en/zdmultilang "ZdMultilang Screencast")
