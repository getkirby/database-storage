<?php

namespace Kirby\DatabaseStorage;

use Kirby\Cms\Pages;
use Kirby\Exception\LogicException;

trait HasDatabaseChildren
{
	public function children(): Pages
	{
		if (!defined(static::class . '::DATABASE_CHILD_MODEL')) {
			throw new LogicException(
				message: static::class . '::DATABASE_CHILD_MODEL must be set',
			);
		}

		return $this->children ??= DatabasePages::fromRows(
			parent: $this,
			model: static::DATABASE_CHILD_MODEL,
			draft: false,
		);
	}

	public function drafts(): Pages
	{
		if (!defined(static::class . '::DATABASE_CHILD_MODEL')) {
			throw new LogicException(
				message: static::class . '::DATABASE_CHILD_MODEL must be set',
			);
		}

		return $this->drafts ??= DatabasePages::fromRows(
			parent: $this,
			model: static::DATABASE_CHILD_MODEL,
			draft: true,
		);
	}
}
