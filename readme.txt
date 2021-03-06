=== Informer ===
Contributors: inpsyde, Bueltge, dnaber-de, hughwillfayle
Tags: mail, comment, post
Requires at least: 3.0
Tested up to: 4.3
Stable tag: 0.0.5

Informs all users of a blog about a new post and approved comments via email

== Description ==
Plugin which sends emails to us from WordPress, for comments and new posts, except our own comments and posts. You can disable the option in your profile. At default, all user are receiving an email except the author.

**Made by [Inpsyde](http://inpsyde.com) &middot; We love WordPress**

Have a look at the premium plugins in our [market](http://marketpress.com).

= Bugs, technical hints or contribute =
Please give me feedback, contribute and file technical bugs on [GitHub Repo](https://github.com/bueltge/Inform-about-Content).


== Installation ==
= Requirements  =
* WordPress version 3.0 and later (see tested up to)
* PHP 5.3; maybe 5.2, but untested

= Installation =
1. Unpack the download-package
1. Upload the folder and all folder and files includes this to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Optional: Change global settings on 'Settings' --> 'Reading'
1. Thats all


== Screenshots ==
1. Settings on profile page
2. Settings on Settings → Reading

== API ==
= Plugin settings =
By default, the plugin sends a mail to all registered users of a blog on new posts or comments, except a user disables the functionality for itself (opt-out). As of version 0.0.5 you can change this behaviour to opt-in with the filter ```iac_default_opt_in``` :
```php
add_filter( 'iac_default_opt_in', '__return_true' );
```
Make shure, this code runs on the action ```plugins_loaded``` with a priority lower than 10 or earlier.

With version 0.0.5 the plugin got settings (Settings→Reading). The one new option allows you to send all emails with the Bcc-header to hide users email-addresses to all other recipients. This option is disabled by default. You have access to the default settings via the filter ```iac_default_options```. An array is passed to this funktion with the key ```send_by_bcc```. Change the value to '1' and return the array on your callback function.

= User settings handling =
To change the users settings (inform about posts, inform about comments) use the action ```iac_save_user_settings``` like this:
```php
do_action(
	'iac_save_user_settings',
	$user_id,
	$inform_about_posts, # '1', '0' or NULL if the user didn't changed anything
	$inform_about_comments # '1', '0' or NULL if the user didn't changed anything
);
```
Getting the current user settings is also easy:
```php
$user_settings = apply_filters( 'iac_get_user_settings', array(), $user_id );
```


== Other Notes ==
= Localizations =
* Thanks to [Frank B&uuml;ltge](http://bueltge.de/ "Frank B&uuml;ltge") for german language file
* Thanks to [Brian Flores](http://www.inmotionhosting.com/) for spanish translation
* Lithuanian translation files by [Vincent G](http://www.host1plus.com)

= Licence =
Good news, this plugin is free for everyone! Since it's released under the GPL, you can use it free of charge on your personal or commercial blog.

= Translations =
The plugin comes with various translations, please refer to the [WordPress Codex](http://codex.wordpress.org/Installing_WordPress_in_Your_Language "Installing WordPress in Your Language") for more information about activating the translation. If you want to help to translate the plugin to your language, please have a look at the .po file which contains all defintions and may be used with a [gettext](http://www.gnu.org/software/gettext/) editor like [Poedit](http://www.poedit.net/) (Windows) or plugin for WordPress [Localization](http://wordpress.org/extend/plugins/codestyling-localization/).


== Changelog ==
= 0.0.6 =
* Change Hook for send only on published posts
* Change Mail-header for use in all systems
* Codestyling
* API for e-mail signature Hook `iac_signature_separator`; Function `append_signature( $message, $signature = '' )`
* BCC possibility
* Add new filters, like shortocodes
* Add settings to global default options on 'Reading'
* Start to handle medias, attachments
* Change autoloader to also usable without spl
* Change hook to `transition_post_status` to change send mail - now send only on new posts, not update posts
* Update language files

= 0.0.5 =
* Option to send email by Bcc-header
* API to change the plugins default behaviour (opt-in/opt-out)
* Fix for update on user profile in WP 3.4*

= 0.0.4 =
* small fix for hook to use static method

= 0.0.3 =
* first release on wp.org

= 0.0.1 =
* Release first version
