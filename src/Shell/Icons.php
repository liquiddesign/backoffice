<?php

declare(strict_types=1);

namespace Backoffice\Shell;

use Nette\Utils\Html;

/**
 * Obrysové ikony 24×24 (stroke, currentColor). Starý admin používal názvy Font Awesome — ty jsou
 * namapované na nejbližší ikonu, ať menu z admin.neon funguje beze změn.
 */
final class Icons
{
	private const PATHS = [
		'dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/>'
			. '<rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
		'news' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h10M7 16h6"/>',
		'settings' => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
		'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
		'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
		'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
		'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
		'external' => '<path d="M15 3h6v6M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'close' => '<path d="M18 6 6 18M6 6l12 12"/>',
		'logo' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
		'dot' => '<circle cx="12" cy="12" r="3"/>',
		'alert' => '<path d="M12 4l9 16H3zM12 10v4M12 17h.01"/>',
		'edit' => '<path d="M4 20h4L19 9l-4-4L4 16zM13 7l4 4"/>',
		'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
		'grip' => '<path d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01"/>',
		'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
		'chevron-up' => '<path d="M6 15l6-6 6 6"/>',
		'check' => '<path d="M5 12l5 5 9-10"/>',
		'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
	];

	private const ALIASES = [
		'newspaper' => 'news',
		'cogs' => 'settings',
		'cog' => 'settings',
		'users-cog' => 'users',
		'user' => 'users',
		'bookmark' => 'file',
		'file-alt' => 'file',
		'home' => 'dashboard',
		'tachometer-alt' => 'dashboard',
	];

	public static function get(?string $name, int $size = 18): Html
	{
		$name = $name === null ? 'dot' : (self::ALIASES[$name] ?? $name);
		$paths = self::PATHS[$name] ?? self::PATHS['dot'];

		return Html::el('svg', [
			'class' => 'bo-ic',
			'width' => $size,
			'height' => $size,
			'viewBox' => '0 0 24 24',
			'fill' => 'none',
			'stroke' => 'currentColor',
			'stroke-width' => 1.8,
			'stroke-linecap' => 'round',
			'stroke-linejoin' => 'round',
			'aria-hidden' => 'true',
		])->setHtml($paths);
	}
}
