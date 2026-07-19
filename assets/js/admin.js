/**
 * RC RaceMap Club Calendar – Admin.
 *
 * Initialisiert den WordPress-Farbwähler für das Akzentfarbe-Feld.
 * Leeres Feld ist erlaubt (= Linkfarbe des Themes).
 *
 * @package RC_RaceMap_Club_Calendar
 */
( function ( $ ) {
	'use strict';

	$( function () {
		if ( $.fn.wpColorPicker ) {
			$( '.rc-rcc-color-field' ).wpColorPicker();
		}
	} );
} )( jQuery );
