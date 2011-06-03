<?php

return array(
	'groups' => array(
		-1	=> array('name' => 'Banned', 'roles' => array('banned')),
		0	=> array('name' => 'Guests', 'roles' => array()),
		1	=> array('name' => 'Users', 'roles' => array('user')),
		50	=> array('name' => 'Moderators', 'roles' => array('user', 'moderator')),
		100	=> array('name' => 'Administrators', 'roles' => array('user', 'moderator', 'admin')),
	),

	'roles' => array(
		'#' => array( // default rights
			'website' => array('read')
		),
		'banned' => false,
		'user' => array(
			'comments' => array('create', 'read'),
			'test' => array('create', 'delete'),
		),
		'moderator' => array(
			'comments' => array('update', 'delete')
		),
		'admin' => array(
			'#' => array('delete'),
			'website' => array('create', 'update', 'delete'),
			'admin' => array('create', 'read', 'update', 'delete'),
			'test' => array('create'),
		),
		'super' => true,
	),

	'table_name' => 'users_gascap',

	/**
	 * Salt for the login hash
	 */
	'login_hash_salt' => 'put_some_salt_in_here',

	/**
	 * Attribute Exchange
	 * email is required
	 */
	'required_info' => array('contact/email'),
	'optional_info' => array(
	),
	'enable_oauth' => true,
	'oauth_consumer_key' => '',
	'oauth_consumer_secret' => '',
	'oauth_url_get_access_token' => 'https://www.google.com/accounts/OAuthGetAccessToken',
	'oauth_scopes' => array(
		'https://www.google.com/calendar/feeds/'
	),
);