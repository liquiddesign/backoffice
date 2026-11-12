<?php

declare(strict_types=1);

use Backoffice\Media\ImageStorage;
use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;
use Nette\Utils\Image;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/**
 * Nahraný soubor jako z formuláře: PNG 800 × 400 v dočasném souboru.
 */
function fakeUpload(string $dir, string $name = 'Fotka Nová.PNG'): FileUpload
{
	$tmp = $dir . '/upload-' . bin2hex(random_bytes(4));
	Image::fromBlank(800, 400, Image::rgb(80, 3, 127))->save($tmp, null, Image::PNG);

	return new FileUpload(['name' => $name, 'full_path' => $name, 'size' => filesize($tmp), 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK]);
}

$dir = sys_get_temp_dir() . '/bo-images-' . getmypid();
FileSystem::delete($dir);
FileSystem::createDir($dir);

test('uloží do všech adresářů s úpravou, jméno = ID + přípona, zavolá onSave', function () use ($dir): void {
	$storage = new ImageStorage($dir, '/userfiles');
	$saved = [];
	$storage->onSave[] = static function (string $path) use (&$saved): void {
		$saved[] = $path;
	};

	$filename = $storage->save(fakeUpload($dir), [
		'cp' => static fn (Image $image) => $image->resize(600, 600),
		'cp/origin' => null,
	], 'abc123');

	Assert::same('abc123.png', $filename);
	Assert::same(600, Image::fromFile("$dir/cp/abc123.png")->getWidth());
	Assert::same(800, Image::fromFile("$dir/cp/origin/abc123.png")->getWidth());
	Assert::same(["$dir/cp/abc123.png", "$dir/cp/origin/abc123.png"], $saved);
	Assert::match('/userfiles/cp/abc123.png?v=%d%', $storage->url('cp', 'abc123.png'));
	Assert::null($storage->url('cp', 'missing.png'));
});

test('smaže kopie i .webp vedle, jméno s cestou ignoruje', function () use ($dir): void {
	$storage = new ImageStorage($dir, '/userfiles');
	$storage->save(fakeUpload($dir), ['del' => null], 'x1');
	file_put_contents("$dir/del/x1.webp", 'webp');
	file_put_contents("$dir/keep.png", 'keep');

	$storage->delete(['del' => null], 'x1.png');
	$storage->delete(['del' => null], '../keep.png');

	Assert::false(is_file("$dir/del/x1.png"));
	Assert::false(is_file("$dir/del/x1.webp"));
	Assert::true(is_file("$dir/keep.png"));
	Assert::null($storage->url('del', '../keep.png'));
});

test('nový obrázek smaže starší .webp, ať ho web nevezme místo nového', function () use ($dir): void {
	$storage = new ImageStorage($dir, '/userfiles');
	FileSystem::createDir("$dir/w");
	file_put_contents("$dir/w/y.webp", 'old');
	$storage->save(fakeUpload($dir), ['w' => null], 'y');

	Assert::false(is_file("$dir/w/y.webp"));
});

FileSystem::delete($dir);
