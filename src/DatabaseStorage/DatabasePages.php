<?php

namespace Kirby\DatabaseStorage;

use Kirby\Cms\Language;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Site;

class DatabasePages extends Pages
{
	public static function fromRows(
		Page|Site $parent,
		string $model,
		bool $draft = false,
	): static {
		$pages    = new static([], $parent);
		$database = DatabaseConnection::for($model);
		$table    = $model::DATABASE_TABLE;

		$rows = $database
			->table($table)
			->where([
				'language' => Language::ensure('default')->code(),
				'parent'   => $parent->uuid()->id(),
				'version'  => 'latest',
				'draft'    => $draft === true ? 1 : 0,
			])
			->order('num ASC, title ASC')
			->all();

		foreach ($rows as $row) {
			$pages->add($model::fromRow($parent, $row));
		}

		return $pages;
	}
}
