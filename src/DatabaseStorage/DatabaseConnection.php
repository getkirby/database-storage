<?php

namespace Kirby\DatabaseStorage;

use Kirby\Cms\App;
use Kirby\Database\Database;
use Kirby\Exception\LogicException;

class DatabaseConnection
{
	public static function for(string $model): Database
	{
		if (!defined($model . '::DATABASE_NAME')) {
			throw new LogicException(
				message: $model . '::DATABASE_NAME must be set in the model class',
			);
		}

		return static::fromName($model::DATABASE_NAME);
	}

	public static function fromName(string $name): Database
	{
		$database = App::instance()->option('database.' . $name);

		if ($database === null) {
			throw new LogicException(
				message: 'A database connection must be set with the key \'database.' . $name . '\' in the config',
			);
		}

		return $database;
	}
}
