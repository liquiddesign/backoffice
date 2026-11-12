<?php

declare(strict_types=1);

namespace Backoffice\Media;

use Nette\Http\FileUpload;
use Nette\Utils\Arrays;
use Nette\Utils\FileSystem;
use Nette\Utils\Image;
use Nette\Utils\Strings;

/**
 * Obrázky agend v userfiles, ve stejném rozložení jako starý admin (liquiddesign/forms UploadImage):
 * userfiles/<adresář>/<soubor>, pro každý adresář pole vlastní kopie s vlastní úpravou.
 *
 * Projekt může po uložení každé kopie něco udělat (WebP vedle originálu) přes $onSave:
 *
 *     decorator:
 *         Backoffice\Media\ImageStorage:
 *             setup:
 *                 - '$onSave[]' = [App\WebpConverter, convert]
 */
final class ImageStorage
{
	/** Přípony, které se uloží (a k nim případný .webp vedle). */
	public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

	/** @var list<callable(string): mixed> zavolá se s cestou každé uložené kopie */
	public array $onSave = [];

	public function __construct(private readonly string $userDir, private readonly string $userUrl)
	{
	}

	/**
	 * Uloží nahraný obrázek do všech adresářů pole jako <basename>.<přípona>; vrátí jméno souboru.
	 * @param array<string, (\Closure(\Nette\Utils\Image): void)|null> $directories
	 */
	public function save(FileUpload $upload, array $directories, string $basename): string
	{
		if (!$upload->isOk() || !$upload->isImage()) {
			throw new \InvalidArgumentException('Soubor není obrázek.');
		}

		if ($directories === []) {
			throw new \LogicException('Pole obrázku nemá adresář — doplňte ->imageDirectory().');
		}

		$extension = Strings::lower((string) \pathinfo($upload->getSanitizedName(), \PATHINFO_EXTENSION));

		if (!Arrays::contains(self::EXTENSIONS, $extension)) {
			$extension = match ($upload->getContentType()) {
				'image/png' => 'png',
				'image/gif' => 'gif',
				'image/webp' => 'webp',
				default => 'jpg',
			};
		}

		$filename = $this->sanitizeBasename($basename) . '.' . $extension;

		foreach ($directories as $directory => $transform) {
			$path = $this->path($directory, $filename);
			FileSystem::createDir(\dirname($path));

			if ($transform === null) {
				FileSystem::copy($upload->getTemporaryFile(), $path);
			} else {
				$image = Image::fromFile($upload->getTemporaryFile());
				$transform($image);
				$image->save($path);
			}

			// Starší .webp vedle by web vzal místo nového obrázku.
			$this->deleteWebp($path);

			foreach ($this->onSave as $callback) {
				$callback($path);
			}
		}

		return $filename;
	}

	/**
	 * Smaže obrázek ze všech adresářů pole (i .webp vedle něj).
	 * @param array<string, mixed> $directories
	 */
	public function delete(array $directories, string $filename): void
	{
		if (!$this->isSafeFilename($filename)) {
			return;
		}

		foreach (\array_keys($directories) as $directory) {
			$path = $this->path($directory, $filename);
			FileSystem::delete($path);
			$this->deleteWebp($path);
		}
	}

	/**
	 * Adresa obrázku pro náhled; s ?v=<čas změny>, ať prohlížeč po nahrání nového neukáže starý.
	 */
	public function url(string $directory, string $filename): ?string
	{
		if (!$this->isSafeFilename($filename)) {
			return null;
		}

		$path = $this->path($directory, $filename);

		if (!\is_file($path)) {
			return null;
		}

		return \rtrim($this->userUrl, '/') . '/' . $directory . '/' . \rawurlencode($filename) . '?v=' . \filemtime($path);
	}

	private function path(string $directory, string $filename): string
	{
		return \rtrim($this->userDir, '/\\') . \DIRECTORY_SEPARATOR . $directory . \DIRECTORY_SEPARATOR . $filename;
	}

	private function deleteWebp(string $path): void
	{
		$webp = (string) \preg_replace('~\.(png|jpe?g|gif)$~i', '.webp', $path);

		if ($webp === $path) {
			return;
		}

		FileSystem::delete($webp);
	}

	private function sanitizeBasename(string $basename): string
	{
		$basename = Strings::webalize($basename, '._', false);

		if ($basename === '') {
			throw new \InvalidArgumentException('Prázdné jméno souboru obrázku.');
		}

		return $basename;
	}

	/**
	 * Jméno souboru z databáze — bez cesty, ať se nedá smazat nic mimo adresář agendy.
	 */
	private function isSafeFilename(string $filename): bool
	{
		return $filename !== '' && !\str_contains($filename, '/') && !\str_contains($filename, '\\') && !\str_starts_with($filename, '.');
	}
}
