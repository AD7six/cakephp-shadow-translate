# Shadow translate

[![Build Status](https://img.shields.io/travis/AD7six/cakephp-shadow-translate/master.svg?style=flat-square)](https://travis-ci.org/AD7six/cakehp-shadow-translate)
[![Coverage Status](https://img.shields.io/coveralls/AD7six/cakephp-shadow-translate/master.svg?style=flat-square)](https://coveralls.io/r/AD7six/cakehp-shadow-translate)
[![Total Downloads](https://img.shields.io/packagist/dt/ad7six/shadow-translate.svg?style=flat-square)](https://packagist.org/packages/ad7six/shadow-translate)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.txt)

This plugin uses a shadow table for translated content, instead of the core's more flexible (but
also potentially quite inefficient) EAV-style translate behavior. The shadow translate behavior
is designed to have the same API as the core's translate behavior making it a drop-in
replacement in terms of usage.

## Quickstart

First install the plugin for your app using composer:

`php composer.phar require ad7six/shadow-translate:dev-master`

Load the plugin by adding following statement to your app's `config/bootstrap.php`:

`Plugin::load('ShadowTranslate');`

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

## Why use Shadow Translate

The standard translate behavior uses an [EAV](https://en.wikipedia.org/wiki/Entity%E2%80%93attribute%E2%80%93value_model)
style translations table, and one join per field. By default all translations
are stored in [the same translation table](https://github.com/cakephp/app/blob/master/config/schema/i18n.sql).
To give an example, the core translation behavior generates sql of the form:

    SELECT
        posts.*,
        posts_title_translations.title,
        posts_title_translations.content,
        etc.
    FROM
        posts
    LEFT JOIN
        i18n as posts_title_translations ON (
            posts_title_translations.locale = "xx" AND
            posts_title_translations.model = "Posts" AND
            posts_title_translations.foreign_key = posts.id AND
            posts_title_translations.field = 'title'
       )
    LEFT JOIN
        i18n as posts_body_translations ON (
            posts_body_translations.locale = "xx" AND
            posts_body_translations.model = "Posts" AND
            posts_body_translations.foreign_key = posts.id AND
            posts_body_translations.field = 'body'
       )
    etc.

There is very little setup for the core translate behavior, but the cost
for no-setup is sql complexity, and it is more complex for each translated
field. Depending on how much data there is being translated - it's quite
possible for this data structure to cause slow queries; it also complicates
finding records by translated field values.

Key points:

 * Easy to setup
 * All translated content stored in the same table
 * One join per translated field when querying
 * Less efficient queries - more joins and one index for all content
 * Harder to find by translated content

By contrast, the shadow translate behavior does not use an EAV style
translation table, the translations are stored in a _copy_ of the main data
table. This permits much less complex sql at the cost of having _some_ setup
steps per table. The shadow translate behavior generates sql of the form:

    SELECT
        posts.*,
        posts_translations.*
    FROM
        posts
    LEFT JOIN
        posts_translations ON (
            posts_translations.locale = "xx" AND
            posts_translations.id = posts.id
    )
    // no etc.

Key points:

 * (Slightly) more work to setup
 * Translated content is stored in a copy of the main data table
 * One join per translated table
 * More efficient queries - less joins and indexes per translated field are possible
 * Easier to find by translated content

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
