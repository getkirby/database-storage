<?php

namespace Kirby\DatabaseStorage;

use Kirby\Cms\Language;
use Kirby\Content\Storage;
use Kirby\Content\VersionId;
use Kirby\Data\Data;
use Kirby\Database\Database;
use Kirby\Database\Query;

class DatabaseStorage extends Storage
{
	protected Database $database;

	public function create(VersionId $versionId, Language $language, array $fields): void
	{
		$this->table()->insert([
			...$this->fields($fields),
			'slug'     => $this->model->slug($language->code()),
			'title'    => $this->model->title()->value(),
			'version'  => $versionId->value(),
			'language' => $language->code(),
			'uuid'     => $this->model->uuid()->id(),
			'parent'   => $this->model->parent()->uuid()->id(),
			'template' => $this->model->intendedTemplate()->name(),
			'num'      => $this->model->num(),
			'draft'    => $this->model->isDraft() ? 1 : 0,
		]);
	}

	protected function database(): Database
	{
		return $this->database ??= DatabaseConnection::for($this->model::class);
	}

	public function delete(VersionId $versionId, Language $language): void
	{
		$this->row($versionId, $language)->delete();
	}

	public function exists(VersionId $versionId, Language $language): bool
	{
		return $this->row($versionId, $language)->first() !== false;
	}

	public function fields(array $fields): array
	{
		$accept   = $this->model::DATABASE_FIELDS;
		$accept[] = 'title';
		$accept[] = 'slug';
		$accept[] = 'lock';

		$fields = array_intersect_key($fields, array_flip($accept));
		$fields = array_map(function ($value) {
			if (is_array($value)) {
				return Data::encode($value, 'yaml');
			}

			return $value;
		}, $fields);

		return $fields;
	}

	public function isSameStorageLocation(
		VersionId $fromVersionId,
		Language $fromLanguage,
		VersionId|null $toVersionId = null,
		Language|null $toLanguage = null,
		Storage|null $toStorage = null
	) {
		// fallbacks to allow keeping the method call lean
		$toVersionId ??= $fromVersionId;
		$toLanguage  ??= $fromLanguage;
		$toStorage   ??= $this;

		// no need to compare entries if the new
		// storage type is different
		if ($toStorage instanceof self === false) {
			return false;
		}

		$idA = $this->read($fromVersionId, $fromLanguage)['id'];
		$idB = $toStorage->read($toVersionId, $toLanguage)['id'];

		return $idA === $idB;
	}

	public function modified(VersionId $versionId, Language $language): int
	{
		return strtotime($this->read($versionId, $language)['modified'] ?? 0);
	}

	/**
	 * Read the original content from disk and merge it with the virtual content
	 */
	public function read(VersionId $versionId, Language $language): array
	{
		$row = $this->row($versionId, $language)->first();

		if ($row === false) {
			return [];
		}

		return $row->toArray();
	}

	public function row(VersionId $versionId, Language $language): Query
	{
		return $this->table()->where([
			'slug'     => $this->model->uid(),
			'version'  => $versionId->value(),
			'language' => $language->code(),
			'parent'   => $this->model->parent()->uuid()->id(),
		]);
	}

	public function rows(array $where = []): Query
	{
		return $this->table()->where([
			'uuid'   => $this->model->uuid()->id(),
			'parent' => $this->model->parent()->uuid()->id(),
			...$where,
		]);
	}

	protected function table(): Query
	{
		return $this->database()->table($this->model::DATABASE_TABLE);
	}

	public function touch(VersionId $versionId, Language $language): void
	{
		$this->row($versionId, $language)->update([
			'modified' => time()
		]);
	}

	protected function write(VersionId $versionId, Language $language, array $fields): void
	{
		$this->row($versionId, $language)->update([
			...$this->fields($fields),
			'modified' => date('Y-m-d H:i:s'),
		]);
	}
}
