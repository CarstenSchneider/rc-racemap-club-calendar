/**
 * RC RaceMap Club Calendar – Admin.
 *
 * Zwei getrennte Aufgaben, je nach Seite:
 *   1. Einstellungen – WordPress-Farbwähler für die Akzentfarbe.
 *      Leeres Feld ist erlaubt (= Linkfarbe des Themes).
 *   2. Rennen verwalten – Medienauswahl für eigene Dokumente und eine
 *      nachwachsende Leerzeile, damit mehrere PDFs ohne Neuladen passen.
 *
 * @package RC_RaceMap_Club_Calendar
 */
( function ( $ ) {
	'use strict';

	var l10n = window.rcRccAdmin || {};

	/**
	 * Die Medienauswahl für eine Dokumentzeile öffnen.
	 *
	 * @param {jQuery} $row Zeile mit Bezeichnungs- und Adressfeld.
	 */
	function pickDocument( $row ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		var frame = window.wp.media( {
			title: l10n.mediaTitle || '',
			button: { text: l10n.mediaButton || '' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var file = frame.state().get( 'selection' ).first().toJSON();

			$row.find( '.rc-rcc-doc-url' ).val( file.url ).trigger( 'change' );

			// Bezeichnung nur vorschlagen, solange der Verein keine eigene gesetzt hat.
			var $label = $row.find( '.rc-rcc-doc-label' );
			if ( ! $label.val() ) {
				$label.val( file.title || '' );
			}
		} );

		frame.open();
	}

	/**
	 * Eine leere Zeile anhängen, sobald die letzte benutzt wird – bis zur
	 * Obergrenze, die die Zelle als data-Attribut mitbringt.
	 *
	 * @param {jQuery} $cell Zelle mit den Dokumentzeilen.
	 */
	function ensureEmptyRow( $cell ) {
		var max = parseInt( $cell.attr( 'data-rc-rcc-max' ), 10 ) || 5;
		var $rows = $cell.find( '.rc-rcc-doc-row' );

		if ( $rows.length >= max ) {
			return;
		}

		var $last = $rows.last();
		if ( ! $last.find( '.rc-rcc-doc-url' ).val() ) {
			return;
		}

		var $clone = $last.clone();
		$clone.find( 'input' ).val( '' );
		$clone.insertAfter( $last );
	}

	/**
	 * Eine leere Terminzeile anhaengen, sobald die letzte benannt wird.
	 * Die Feldnamen tragen einen Index, der beim Klonen hochgezaehlt wird.
	 *
	 * @param {jQuery} $body Tabellenkoerper der eigenen Termine.
	 */
	function ensureEmptyRaceRow( $body ) {
		var max = parseInt( $body.attr( 'data-rc-rcc-max' ), 10 ) || 50;
		var $rows = $body.find( '.rc-rcc-own-row' );

		if ( $rows.length >= max ) {
			return;
		}

		var $last = $rows.last();
		if ( ! $last.find( '.rc-rcc-own-title' ).val() ) {
			return;
		}

		var index = $rows.length;
		var $clone = $last.clone();

		$clone.find( 'input' ).each( function () {
			var $field = $( this );
			var name = $field.attr( 'name' ) || '';

			$field.val( '' );
			$field.attr( 'name', name.replace( /\[\d+\]/, '[' + index + ']' ) );
		} );

		$body.append( $clone );
	}

	$( function () {
		if ( $.fn.wpColorPicker ) {
			$( '.rc-rcc-color-field' ).wpColorPicker();
		}

		$( document ).on( 'click', '.rc-rcc-doc-pick', function ( event ) {
			event.preventDefault();
			pickDocument( $( this ).closest( '.rc-rcc-doc-row' ) );
		} );

		$( document ).on( 'change input', '.rc-rcc-doc-url', function () {
			ensureEmptyRow( $( this ).closest( '.rc-rcc-docs-cell' ) );
		} );

		$( document ).on( 'change input', '.rc-rcc-own-title', function () {
			ensureEmptyRaceRow( $( this ).closest( '.rc-rcc-own-body' ) );
		} );
	} );
} )( jQuery );
