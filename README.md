# Shadow translate

[![Build Status](https://travis-ci.org/AD7six/cakephp-shadow-translate.png?branch=master)](https://travis-ci.org/AD7six/cakephp-shadow-translate)
[![Coverage Status](https://coveralls.io/repos/AD7six/cakephp-shadow-translate/badge.png)](https://coveralls.io/r/AD7six/cakephp-shadow-translate)

This plugin uses a shadow table for translated content, instead of the core's more flexible (but
also potentially quite inefficient) EAV-style translate behavior. The shadow translate behavior
is designed to have the same API as the core's translate behavior making it a drop-in
replacement in terms of usage.

## Quickstart

The shadow translate behavior expects each table to have its own translation table. Taking the
blog tutorial as a start point, the following table would already exist:

	CREATE TABLE posts (
		id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		title VARCHAR(50),
		body TEXT,
		created DATETIME DEFAULT NULL,
		modified DATETIME DEFAULT NULL
	);

To prepare for using the shadow translate behavior, the following table would be created:

	CREATE TABLE posts_translations (
		id INT UNSIGNED,
		locale VARCHAR(5),
		title VARCHAR(50),
		body TEXT,
		PRIMARY KEY (id, locale)
	);

Note that the id is the same type as the posts table - but the primary key is a compound key
using both id and locale.

Usage is very similar to the core's behavior so e.g.:

	class PostsTable extends Table {

		public function initialize(array $config) {
			$this->addBehavior('ShadowTranslate.ShadowTranslate');
		}
	}

You can specify the fields in the translation table - but if you don't they are derived from the translation
table schema. From this point forward, see [the documentation for the core translate behavior](http://book.cakephp.org/3.0/en/orm/behaviors/translate.html), the shadow translate behavior should act
the same, and if it doesn't, well, see  below.

## Roadmap

The initial release is only the behavior, it is planned for the future to add:

 * A shell to create shadow tables (migration based)
 * A shell to import from the core's i18n table
 * A shell to export to the core's i18n table

## Bugs

Most likely!

If you happen to stumble upon a bug, please feel free to create a pull request with a fix
(optionally with a test), and a description of the bug and how it was resolved.

You can also create an issue with a description to raise awareness of the bug.

## Support / Questions

If you have a problem, if no one else can help, and if you can find them, maybe you can
find help on IRC in the #FriendsOfCake channel.
