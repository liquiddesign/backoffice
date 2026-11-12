<?php

declare(strict_types=1);

use Backoffice\Media\FileStorage;
use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

function pdfUpload(string $dir, string $name): FileUpload
{
	$tmp = $dir . '/upload-' . bin2hex(random_bytes(4));
	file_put_contents($tmp, "%PDF-1.4\n%test\n");

	return new FileUpload(['name' => $name, 'full_path' => $name, 'size' => filesize($tmp), 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK]);
}

$dir = sys_get_temp_dir() . '/bo-files-' . getmypid();
FileSystem::delete($dir);
FileSystem::createDir($dir);

test('jméno z nahraného souboru, cizí soubor se nepřepíše, vlastní ano', function () use ($dir): void {
	$storage = new FileStorage("$dir/u", '/userfiles', "$dir/trash");

	// Jako starý admin: FileUpload::getSanitizedName() (bez diakritiky, velikost písmen zůstane).
	Assert::same('Vyrocni-zprava-2025.pdf', $storage->save(pdfUpload($dir, 'Výroční zpráva 2025.PDF'), 'ar'));
	Assert::same('Vyrocni-zprava-2025-2.pdf', $storage->save(pdfUpload($dir, 'Výroční zpráva 2025.pdf'), 'ar'));
	Assert::same('Vyrocni-zprava-2025.pdf', $storage->save(pdfUpload($dir, 'Výroční zpráva 2025.pdf'), 'ar', 'Vyrocni-zprava-2025.pdf'));
	Assert::same('/userfiles/ar/Vyrocni-zprava-2025.pdf', $storage->url('ar', 'Vyrocni-zprava-2025.pdf'));
	Assert::same(15, $storage->size('ar', 'Vyrocni-zprava-2025.pdf'));
});

test('koš: přesun mimo web a vrácení', function () use ($dir): void {
	$storage = new FileStorage("$dir/u", '/userfiles', "$dir/trash");
	$storage->save(pdfUpload($dir, 'smazat.pdf'), 'ar');

	$key = $storage->trash('ar', 'smazat.pdf');
	Assert::type('string', $key);
	Assert::false(is_file("$dir/u/ar/smazat.pdf"));
	Assert::true(is_file("$dir/trash/$key"));

	Assert::true($storage->restore((string) $key, 'ar', 'smazat.pdf'));
	Assert::true(is_file("$dir/u/ar/smazat.pdf"));
	Assert::false($storage->restore((string) $key, 'ar', 'smazat.pdf'));
	Assert::null($storage->trash('ar', 'neni.pdf'));
	Assert::null($storage->trash('ar', '../u/ar/smazat.pdf'));
});

test('starý soubor v koši se při dalším mazání uklidí', function () use ($dir): void {
	$storage = new FileStorage("$dir/u", '/userfiles', "$dir/trash");
	FileSystem::write("$dir/trash/old-entry.pdf", 'x');
	touch("$dir/trash/old-entry.pdf", time() - 2 * 86400);
	$storage->save(pdfUpload($dir, 'dalsi.pdf'), 'ar');
	$storage->trash('ar', 'dalsi.pdf');

	Assert::false(is_file("$dir/trash/old-entry.pdf"));
});

FileSystem::delete($dir);
