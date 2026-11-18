<?php

declare(strict_types=1);

namespace Backoffice\Resource\Form;

/**
 * Editor formátovaného textu, který do polí richText / localeRichText dodává aplikace.
 *
 * Balík sám žádný editor nenese: vykreslí textarea s nastavením z config() a skripty ze scripts()
 * načte, až je na stránce pole s formátovaným textem. Poslední skript musí nastavit
 * window.BackofficeRichText = { init(textarea, config, changed), save() } — init() editor
 * nad textarea spustí (changed() zavolá při každé úpravě, kvůli hlášce „Neuložené změny“),
 * save() přepíše obsah všech editorů zpátky do textarea před odesláním formuláře.
 */
interface RichTextEditor
{
	/**
	 * Adresy skriptů v pořadí načtení.
	 * @return list<string>
	 */
	public function scripts(): array;

	/**
	 * Nastavení editoru pro jedno pole; $mutation je jazyk u localeRichText, jinak null.
	 * Field::getEditorOptions() se přidá navrch.
	 * @return array<string, mixed>
	 */
	public function config(Field $field, ?string $mutation): array;
}
