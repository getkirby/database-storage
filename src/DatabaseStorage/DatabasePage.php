<?php

namespace Kirby\DatabaseStorage;

use Kirby\Cms\Language;
use Kirby\Cms\ModelState;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Cms\Url;
use Kirby\Content\ImmutableMemoryStorage;
use Kirby\Content\Storage;
use Kirby\Content\VersionCache;
use Kirby\Exception\LogicException;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Obj;

abstract class DatabasePage extends Page
{
	public const DATABASE_NAME = '';
	public const DATABASE_TABLE = '';
	public const DATABASE_FIELDS = [];

	public function changeNum(int|null $num = null): static
	{
		if ($this->isDraft() === true) {
			throw new LogicException(
				message: 'Drafts cannot change their sorting number'
			);
		}

		// don't run the action if everything stayed the same
		if ($this->num() === $num) {
			return $this;
		}

		return $this->commit('changeNum', ['page' => $this, 'num' => $num], function ($oldPage, $num) {
			$newPage = $oldPage->clone([
				'num'      => $num,
				'dirname'  => null,
				'root'     => null,
				'template' => $oldPage->intendedTemplate()->name(),
			]);

			$oldPage->storage()->rows()->update(['num' => $num]);

			return $newPage;
		});
	}

	public function changeSlug(
		string $slug,
		string|null $languageCode = null
	): static {
		$slug     = Url::slug($slug);
		$language = Language::ensure($languageCode ?? 'current');
		$code     = $language->code();

		if ($language->isDefault() === false) {
			return $this->changeSlugForLanguage($slug, $code);
		}

		if ($slug === $this->slug()) {
			return $this;
		}

		$arguments = [
			'page'         => $this,
			'slug'         => $slug,
			'languageCode' => null,
			'language'     => $language
		];

		return $this->commit('changeSlug', $arguments, function ($oldPage, $slug, $languageCode, $language) {
			$newPage = $oldPage->clone([
				'slug'     => $slug,
				'dirname'  => null,
				'root'     => null,
				'template' => $oldPage->intendedTemplate()->name(),
			]);

			// clear UUID cache recursively (for children and files as well)
			$oldPage->uuid()?->clear(true);

			if ($oldPage->exists() === true) {
				$oldPage->storage()->rows()->update(['slug' => $slug]);

				// hard reset for the version cache
				// to avoid broken/overlapping page references
				VersionCache::reset();

				// remove from the siblings
				ModelState::update(
					method: 'remove',
					current: $oldPage,
				);

				Dir::remove($oldPage->mediaRoot());
			}

			return $newPage;
		});
	}

	public function copy(array $options = []): static
	{
		throw new LogicException('Copying a page in a database is not supported yet');
	}

	/**
	 * We only need this to fix the broken logic when changeStorage is called
	 * without copy: true. This will lead to an infinite loop because of UUID retreival when untracking changes.
	 * We might want to fix this in the core.
	 */
	public function delete(bool $force = false): bool
	{
		return $this->commit('delete', ['page' => $this, 'force' => $force], function ($page, $force) {
			$old = $page->clone();

			// keep the content in iummtable memory storage
			// to still have access to it in after hooks
			$page->changeStorage(ImmutableMemoryStorage::class, copy: true);

			// clear UUID cache
			$page->uuid()?->clear();

			// delete all files individually
			foreach ($old->files() as $file) {
				$file->delete();
			}

			// delete all children individually
			foreach ($old->childrenAndDrafts() as $child) {
				$child->delete(true);
			}

			// delete all versions,
			// the plain text storage handler will then clean
			// up the directory if it's empty
			$old->versions()->delete();

			if (
				$old->isListed() === true &&
				$old->blueprint()->num() === 'default'
			) {
				$old->resortSiblingsAfterUnlisting();
			}

			return true;
		});
	}

	public function dirname(): string
	{
		return $this->dirname ??= $this->uuid()->id();
	}

	public function diruri(): string
	{
		if (is_string($this->diruri) === true) {
			return $this->diruri;
		}

		$dirname = $this->dirname();

		if ($parent = $this->parent()) {
			return $this->diruri = $parent->diruri() . '/' . $dirname;
		}

		return $this->diruri = $dirname;
	}

	/**
	 * Checks if the page exists in the database
	 */
	public function exists(): bool
	{
		if ($this->storage() instanceof DatabaseStorage) {
			return $this->storage()->rows()->first() !== false;
		}

		return parent::exists();
	}

	public static function fromRow(Site|Page $parent, Obj $row): static
	{
		$page = new static([
			'slug'     => $row->slug(),
			'template' => $row->template(),
			'num'      => $row->num() === null ? null : (int)($row->num()),
			'isDraft'  => (int)($row->draft()) === 1,
			'parent'   => $parent
		]);

		// prefill the version cache with the content. Otherwise
		// the storage would need to read the same row twice
		VersionCache::set(
			$page->version(),
			Language::ensure('default'),
			$row->toArray()
		);

		$page->uuid();

		return $page;
	}

	public function move(Site|Page $parent): Page
	{
		throw new LogicException('Moving a page in a database is not supported yet');
	}

	public function publish(): static
	{
		if ($this->isDraft() === false) {
			return $this;
		}

		$page = $this->clone([
			'isDraft'  => false,
			'root'     => null,
			'template' => $this->intendedTemplate()->name(),
		]);

		$page->storage()->rows()->update(['draft' => 0]);

		$parentModel = $page->parentModel();
		$parentModel->drafts()->remove($page);
		$parentModel->children()->append($page->id(), $page);

		// update the childrenAndDrafts() cache if it is initialized
		if ($parentModel->childrenAndDrafts !== null) {
			$parentModel->childrenAndDrafts()->set($page->id(), $page);
		}

		return $page;
	}

	public function storage(): Storage
	{
		return $this->storage ??= new DatabaseStorage($this);
	}

	public function unpublish(): static
	{
		if ($this->isDraft() === true) {
			return $this;
		}

		$page = $this->clone([
			'isDraft'  => true,
			'num'      => null,
			'dirname'  => null,
			'root'     => null,
			'template' => $this->intendedTemplate()->name(),
		]);

		$page->storage()->rows()->update(['draft' => 1, 'num' => null]);

		// remove the page from the parent children and add it to drafts
		$parentModel = $page->parentModel();
		$parentModel->children()->remove($page);
		$parentModel->drafts()->append($page->id(), $page);

		// update the childrenAndDrafts() cache if it is initialized
		if ($parentModel->childrenAndDrafts !== null) {
			$parentModel->childrenAndDrafts()->set($page->id(), $page);
		}

		$page->resortSiblingsAfterUnlisting();

		return $page;
	}
}
