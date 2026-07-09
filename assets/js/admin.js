/**
 * RC RaceMap Club Calendar – Admin.
 *
 * Initialisiert den WordPress-Farbwähler (Iris) für das Akzentfarbe-Feld.
 * Leeres Feld ist erlaubt (= automatisch die Theme-Linkfarbe).
 *
 * @package RC_RaceMap_Club_Calendar
 */
( function ( $ ) {
	'use strict';

	$( function () {
		if ( ! $.fn.wpColorPicker ) {
			return;
		}

		$( '.rc-rcc-color-field' ).wpColorPicker();
	} );
} )( jQuery );
