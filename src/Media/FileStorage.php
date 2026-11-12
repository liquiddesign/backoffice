<?php

declare(strict_types=1);

namespace Backoffice\Media;

use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;
use Nette\Utils\Random;
use Nette\Utils\Strings;

/**
 * Soubory agend (PDF ke stažení…) v userfiles/<adresář>/<jméno>, jako starý admin: jméno je
 * očištěné jméno nahraného souboru. Když už soubor s tím jménem patří jinému záznamu, dostane
 * příponu -2, -3… (starý admin ho přepsal).
 *
 * Smazaný soubor se nemaže hned, ale přesune do koše mimo web (temp), ať ho „Vrátit“ může vrátit;
 * koš se čistí po jednom dni.
 */
final class FileStorage
{
	private const TRASH_TTL = 86400;

	public function __construct(
		private readonly string $userDir,
		private readonly string $userUrl,
		private readonly string $trashDir,
	) {
	}

	/**
	 * Uloží nahraný soubor; $current = soubor, který záznam už má (smí se přepsat stejným jménem).
	 */
	public function save(FileUpload $upload, string $directory, ?string $current = null): string
	{
		if (!$upload->isOk()) {
			throw new \InvalidArgumentException('Soubor se nenahrál.');
		}

		$name = $upload->getSanitizedName();
		$base = (string) \pathinfo($name, \PATHINFO_FILENAME);
		$extension = Strings::lower((string) \pathinfo($name, \PATHINFO_EXTENSION));
		$base = $base === '' || \str_starts_with($base, '.') ? 'soubor' : $base;
		$filename = $base . ($extension !== '' ? '.' . $extension : '');

		for ($i = 2; \is_file($this->path($directory, $filename)) && $filename !== $current; $i++) {
			$filename = $base . '-' . $i . ($extension !== '' ? '.' . $extension : '');
		}

		$path = $this->path($directory, $filename);
		FileSystem::createDir(\dirname($path));
		FileSystem::copy($upload->getTemporaryFile(), $path);

		return $filename;
	}

	public function delete(string $directory, string $filename): void
	{
		if (!self::isSafeFilename($filename)) {
			return;
		}

		FileSystem::delete($this->path($directory, $filename));
	}

	/**
	 * Přesune soubor do koše; vrátí klíč pro restore(), nebo null, když soubor není.
	 */
	public function trash(string $directory, string $filename): ?string
	{
		$path = $this->path($directory, $filename);

		if (!self::isSafeFilename($filename) || !\is_file($path)) {
			return null;
		}

		$this->purgeTrash();
		$key = \time() . '-' . Random::generate(10) . '-' . $filename;
		FileSystem::createDir($this->trashDir);
		FileSystem::rename($path, $this->trashDir . \DIRECTORY_SEPARATOR . $key);
		// Koš se čistí podle času změny — ten má být čas smazání, ne nahrání.
		\touch($this->trashDir . \DIRECTORY_SEPARATOR . $key);

		return $key;
	}

	/**
	 * Vrátí soubor z koše na původní místo. False, když v koši už není nebo místo je obsazené.
	 */
	public function restore(string $key, string $directory, string $filename): bool
	{
		$source = $this->trashDir . \DIRECTORY_SEPARATOR . $key;
		$target = $this->path($directory, $filename);

		if (!self::isSafeFilename($key) || !self::isSafeFilename($filename) || !\is_file($source) || \is_file($target)) {
			return false;
		}

		FileSystem::createDir(\dirname($target));
		FileSystem::rename($source, $target);

		return true;
	}

	public function url(string $directory, string $filename): ?string
	{
		if (!self::isSafeFilename($filename) || !\is_file($this->path($directory, $filename))) {
			return null;
		}

		return \rtrim($this->userUrl, '/') . '/' . $directory . '/' . \rawurlencode($filename);
	}

	public function size(string $directory, string $filename): ?int
	{
		$path = $this->path($directory, $filename);

		return self::isSafeFilename($filename) && \is_file($path) ? (int) \filesize($path) : null;
	}

	/**
	 * Jméno souboru z databáze — bez cesty, ať se nedá sáhnout mimo adresář agendy.
	 */
	public static function isSafeFilename(string $filename): bool
	{
		return $filename !== '' && !\str_contains($filename, '/') && !\str_contains($filename, '\\') && !\str_starts_with($filename, '.');
	}

	private function purgeTrash(): void
	{
		if (!\is_dir($this->trashDir)) {
			return;
		}

		foreach (new \DirectoryIterator($this->trashDir) as $item) {
			if (!$item->isFile() || $item->getMTime() > \time() - self::TRASH_TTL) {
				continue;
			}

			FileSystem::delete($item->getPathname());
		}
	}

	private function path(string $directory, string $filename): string
	{
		return \rtrim($this->userDir, '/\\') . \DIRECTORY_SEPARATOR . $directory . \DIRECTORY_SEPARATOR . $filename;
	}
}
