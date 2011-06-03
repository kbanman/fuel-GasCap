To install, merge the app and packages directories from this repo with those in your `fuel` directory.

Then, in `app/config/auth.php`, set the `driver` parameter to be `openid`.

Use the following SQL to create the necessary table:

	CREATE TABLE `users_openid` (
	  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	  `created_at` int(11) unsigned DEFAULT NULL,
	  `updated_at` int(11) unsigned DEFAULT NULL,
	  `group` int(11) NOT NULL DEFAULT '1',
	  `email` varchar(128) NOT NULL,
	  `last_login` varchar(25) DEFAULT NULL,
	  `login_hash` varchar(255) DEFAULT NULL,
	  `oauth_token` varchar(255) DEFAULT NULL,
	  `oauth_token_secret` varchar(255) DEFAULT NULL,
	  `identifier` varchar(255) DEFAULT NULL,
	  PRIMARY KEY (`id`),
	  KEY `email` (`email`),
	  KEY `identifier` (`identifier`)
	) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

The example code is currently modelled so that users must be "invited" to create an account;  
An activation code is generated and attached to a group, and the user can activate that account with any supported OpenID identity.  

This is all still in a very alpha state; I'll be adding better example code and updates as I have time.