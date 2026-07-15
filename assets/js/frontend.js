/**
 * RC RaceMap Club Calendar – Frontend-Interaktion.
 *
 * Zwei Ebenen ohne Seitenreload:
 *   1. Haupt-Tabs   „Aktuelle Termine" / „Archiv"
 *   2. Jahres-Pills innerhalb jedes Tabs
 *
 * Keine externen Abhängigkeiten. Mehrere Kalender-Instanzen pro Seite werden
 * unabhängig behandelt; die Jahres-Navigation ist auf ihr Tab-Panel begrenzt.
 *
 * @package RC_RaceMap_Club_Calendar
 */
( function () {
	'use strict';

	function slice( nodeList ) {
		return Array.prototype.slice.call( nodeList );
	}

	/**
	 * Wire a set of tab-like buttons to their panels (generic, reused for the
	 * main tabs and the year navigation).
	 *
	 * @param {HTMLElement[]} buttons   Buttons carrying btnAttr.
	 * @param {HTMLElement[]} panels    Panels carrying panelAttr.
	 * @param {string}        btnAttr   Attribute holding a button's key.
	 * @param {string}        panelAttr Attribute holding a panel's key.
	 */
	function wireGroup( buttons, panels, btnAttr, panelAttr ) {
		if ( ! buttons.length || ! panels.length ) {
			return;
		}

		function activate( name, focusButton ) {
			buttons.forEach( function ( button ) {
				var isActive = button.getAttribute( btnAttr ) === name;
				button.classList.toggle( 'is-active', isActive );
				button.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
				button.setAttribute( 'tabindex', isActive ? '0' : '-1' );
				if ( isActive && focusButton ) {
					button.focus();
				}
			} );

			panels.forEach( function ( panel ) {
				var isActive = panel.getAttribute( panelAttr ) === name;
				panel.classList.toggle( 'is-active', isActive );
				if ( isActive ) {
					panel.removeAttribute( 'hidden' );
				} else {
					panel.setAttribute( 'hidden', 'hidden' );
				}
			} );
		}

		buttons.forEach( function ( button, index ) {
			button.addEventListener( 'click', function () {
				activate( button.getAttribute( btnAttr ), false );
			} );

			button.addEventListener( 'keydown', function ( event ) {
				var newIndex = null;

				switch ( event.key ) {
					case 'ArrowRight':
					case 'ArrowDown':
						newIndex = ( index + 1 ) % buttons.length;
						break;
					case 'ArrowLeft':
					case 'ArrowUp':
						newIndex = ( index - 1 + buttons.length ) % buttons.length;
						break;
					case 'Home':
						newIndex = 0;
						break;
					case 'End':
						newIndex = buttons.length - 1;
						break;
					default:
						return;
				}

				event.preventDefault();
				activate( buttons[ newIndex ].getAttribute( btnAttr ), true );
			} );
		} );
	}

	/**
	 * Klassen-Tags ab N Stück einklappen und einen „+X weitere"-Umschalter
	 * anhängen (fällt ohne JS auf „alle sichtbar" zurück).
	 *
	 * @param {HTMLElement} root Wurzelelement ([data-rc-rcc]).
	 */
	function collapseClasses( root ) {
		var limit = 4;
		var i18n = window.rcRccData || {};
		var moreTpl = i18n.more || '+%d';
		var lessTxt = i18n.less || '–';

		slice( root.querySelectorAll( '[data-rc-rcc-classes]' ) ).forEach( function ( list ) {
			var tags = slice( list.querySelectorAll( '.rc-rcc__class' ) );
			if ( tags.length <= limit + 1 ) {
				return;
			}

			var hidden = tags.slice( limit );
			hidden.forEach( function ( tag ) {
				tag.hidden = true;
			} );

			var moreLabel = moreTpl.replace( '%d', hidden.length );
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'rc-rcc__class-more';
			btn.textContent = moreLabel;
			btn.setAttribute( 'aria-expanded', 'false' );

			btn.addEventListener( 'click', function () {
				var expand = hidden[0].hidden;
				hidden.forEach( function ( tag ) {
					tag.hidden = ! expand;
				} );
				btn.textContent = expand ? lessTxt : moreLabel;
				btn.setAttribute( 'aria-expanded', expand ? 'true' : 'false' );
			} );

			list.appendChild( btn );
		} );
	}

	/**
	 * Initialise a single calendar instance.
	 *
	 * @param {HTMLElement} root Wurzelelement ([data-rc-rcc]).
	 */
	function initCalendar( root ) {

		collapseClasses( root );

		// Level 1: main tabs.
		wireGroup(
			slice( root.querySelectorAll( '[data-rc-rcc-tab]' ) ),
			slice( root.querySelectorAll( '[data-rc-rcc-panel]' ) ),
			'data-rc-rcc-tab',
			'data-rc-rcc-panel'
		);

		// Level 2: year navigation, scoped to each main panel.
		slice( root.querySelectorAll( '[data-rc-rcc-panel]' ) ).forEach( function ( panel ) {
			wireGroup(
				slice( panel.querySelectorAll( '[data-rc-rcc-year]' ) ),
				slice( panel.querySelectorAll( '[data-rc-rcc-year-panel]' ) ),
				'data-rc-rcc-year',
				'data-rc-rcc-year-panel'
			);
		} );
	}

	function init() {
		slice( document.querySelectorAll( '[data-rc-rcc]' ) ).forEach( initCalendar );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
