<?php

use Kirby\CLI\CLI;
use Kirby\DatabaseStorage\DatabaseConnection;
use Kirby\Toolkit\Str;

return [
	'description' => 'Creates a new table',
	'args' => [
		'database' => [
			'description' => 'The name of the database to create the table in'
		],
		'name' => [
			'description' => 'The name of the table to create'
		],
	],
	'command' => static function (CLI $cli): void {
		$database = $cli->argOrPrompt(
			'database',
			'Which database should the table be created in?'
		);

		$connection = DatabaseConnection::fromName($database);

		$name = $cli->argOrPrompt(
			'name',
			'What is the name of the table to create?'
		);

		$fields = $cli->prompt(
			'Custom fields (separated by comma)'
		);

		$fields = Str::split($fields);

		$fields = array_map(function ($field) {
			return "`$field` TEXT";
		}, $fields);

		$fields = implode(", \n", $fields);

		$connection->fail(true)->query(<<<SQL
			CREATE TABLE "$name" (
				"id" INTEGER UNIQUE NOT NULL PRIMARY KEY ASC AUTOINCREMENT,
				"title" TEXT,
				"slug" TEXT NOT NULL,
				"uuid" TEXT NOT NULL,
				"created" TEXT DEFAULT CURRENT_TIMESTAMP NOT NULL,
				"modified" TEXT DEFAULT CURRENT_TIMESTAMP NOT NULL,
				"version" TEXT NOT NULL,
				"language" TEXT NOT NULL,
				"parent" TEXT,
				"template" TEXT,
				"num" INTEGER DEFAULT NULL,
				"lock" TEXT,
				"draft" INTEGER DEFAULT 1 NOT NULL,
				$fields
			);
		SQL);

		$cli->success('Table ' . $name . ' created in database ' . $database);
	}
];
