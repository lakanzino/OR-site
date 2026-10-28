/**
 * Compact, keyboard-operable presentation for the two care selects.
 * The original select remains the source of truth and is never removed.
 * If this script fails to load, the native select remains available.
 */
( function () {
	'use strict';

	function enhance( select ) {
		if ( ! select || ! select.id || ! select.options.length || select.hidden || select.disabled ) {
			return;
		}
		var label = document.querySelector( 'label[for="' + select.id + '"]' );
		if ( ! label ) {
			return;
		}

		var wrapper = document.createElement( 'div' );
		var button = document.createElement( 'button' );
		var value = document.createElement( 'span' );
		var list = document.createElement( 'ul' );
		var choices = [];
		var active = select.selectedIndex;
		var search = '';
		var searchTime = 0;

		label.id = label.id || select.id + '-label';
		wrapper.className = 'hj-care-select';
		button.type = 'button';
		button.className = 'hj-care-select__button';
		button.setAttribute( 'role', 'combobox' );
		button.setAttribute( 'aria-haspopup', 'listbox' );
		button.setAttribute( 'aria-expanded', 'false' );
		button.setAttribute( 'aria-controls', select.id + '-listbox' );
		value.id = select.id + '-value';
		value.className = 'hj-care-select__value';
		button.setAttribute( 'aria-labelledby', label.id + ' ' + value.id );
		button.appendChild( value );
		list.id = select.id + '-listbox';
		list.className = 'hj-care-select__list';
		list.setAttribute( 'role', 'listbox' );
		list.setAttribute( 'aria-labelledby', label.id );
		list.hidden = true;

		for ( var i = 0; i < select.options.length; i++ ) {
			var original = select.options[ i ];
			var choice = document.createElement( 'li' );
			var groupDisabled = original.parentElement && original.parentElement.tagName === 'OPTGROUP' && original.parentElement.disabled;
			choice.id = select.id + '-option-' + i;
			choice.className = 'hj-care-select__option';
			choice.setAttribute( 'role', 'option' );
			choice.setAttribute( 'aria-selected', 'false' );
			choice.setAttribute( 'data-index', String( i ) );
			if ( original.disabled || groupDisabled ) {
				choice.setAttribute( 'aria-disabled', 'true' );
			}
			choice.textContent = original.textContent;
			list.appendChild( choice );
			choices.push( choice );
		}

		function isEnabled( index ) {
			return choices[ index ] && choices[ index ].getAttribute( 'aria-disabled' ) !== 'true';
		}

		function firstEnabled( reverse ) {
			var index = reverse ? choices.length - 1 : 0;
			var step = reverse ? -1 : 1;
			while ( index >= 0 && index < choices.length ) {
				if ( isEnabled( index ) ) { return index; }
				index += step;
			}
			return -1;
		}

		function updateValue() {
			var selected = select.options[ select.selectedIndex ];
			value.textContent = selected ? selected.textContent : '';
		}

		function activate( index ) {
			if ( ! isEnabled( index ) ) { return; }
			active = index;
			choices.forEach( function ( choice, choiceIndex ) {
				var current = choiceIndex === active;
				choice.classList.toggle( 'is-active', current );
				choice.setAttribute( 'aria-selected', String( current ) );
			} );
			button.setAttribute( 'aria-activedescendant', choices[ active ].id );
			var top = choices[ active ].offsetTop;
			var bottom = top + choices[ active ].offsetHeight;
			if ( top < list.scrollTop ) {
				list.scrollTop = top;
			} else if ( bottom > list.scrollTop + list.clientHeight ) {
				list.scrollTop = bottom - list.clientHeight;
			}
		}

		function close() {
			list.hidden = true;
			wrapper.classList.remove( 'hj-care-select--above' );
			button.setAttribute( 'aria-expanded', 'false' );
			button.removeAttribute( 'aria-activedescendant' );
			search = '';
		}

		function open() {
			var initial = isEnabled( select.selectedIndex ) ? select.selectedIndex : firstEnabled( false );
			if ( initial < 0 ) { return; }
			var rect = button.getBoundingClientRect();
			var below = window.innerHeight - rect.bottom;
			wrapper.classList.toggle( 'hj-care-select--above', below < 180 && rect.top > below );
			list.hidden = false;
			button.setAttribute( 'aria-expanded', 'true' );
			activate( initial );
		}

		function move( direction ) {
			var index = active;
			for ( var count = 0; count < choices.length; count++ ) {
				index = ( index + direction + choices.length ) % choices.length;
				if ( isEnabled( index ) ) { activate( index ); return; }
			}
		}

		function commit( index ) {
			if ( ! isEnabled( index ) ) { return; }
			var changed = select.selectedIndex !== index;
			select.selectedIndex = index;
			updateValue();
			close();
			if ( changed ) {
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
			button.focus();
		}

		button.addEventListener( 'click', function () {
			if ( list.hidden ) { open(); } else { close(); }
		} );
		button.addEventListener( 'keydown', function ( event ) {
			var key = event.key;
			if ( key === 'ArrowDown' || key === 'ArrowUp' ) {
				event.preventDefault();
				if ( list.hidden ) { open(); } else { move( key === 'ArrowDown' ? 1 : -1 ); }
			} else if ( ! list.hidden && ( key === 'Home' || key === 'End' ) ) {
				event.preventDefault();
				activate( firstEnabled( key === 'End' ) );
			} else if ( ! list.hidden && ( key === 'Enter' || key === ' ' || key === 'Spacebar' ) ) {
				event.preventDefault();
				commit( active );
			} else if ( ! list.hidden && key === 'Escape' ) {
				event.preventDefault();
				event.stopPropagation();
				close();
			} else if ( key === 'Tab' ) {
				close();
			} else if ( key.length === 1 && key !== ' ' && ! event.ctrlKey && ! event.metaKey && ! event.altKey ) {
				var now = Date.now();
				search = ( now - searchTime < 700 ? search : '' ) + key.toLocaleLowerCase();
				searchTime = now;
				if ( list.hidden ) { open(); }
				for ( var n = 0; n < choices.length; n++ ) {
					if ( isEnabled( n ) && choices[ n ].textContent.trim().toLocaleLowerCase().indexOf( search ) === 0 ) {
						activate( n );
						break;
					}
				}
			}
		} );
		list.addEventListener( 'mousedown', function ( event ) { event.preventDefault(); } );
		list.addEventListener( 'click', function ( event ) {
			var choice = event.target.closest( '[role="option"]' );
			if ( choice && list.contains( choice ) ) {
				commit( Number( choice.getAttribute( 'data-index' ) ) );
			}
		} );
		label.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			button.focus();
		} );
		select.addEventListener( 'change', updateValue );
		document.addEventListener( 'pointerdown', function ( event ) {
			if ( ! wrapper.contains( event.target ) ) { close(); }
		} );
		document.addEventListener( 'focusin', function ( event ) {
			if ( ! wrapper.contains( event.target ) ) { close(); }
		} );

		updateValue();
		wrapper.appendChild( button );
		wrapper.appendChild( list );
		select.parentNode.insertBefore( wrapper, select.nextSibling );
		// Hide the fallback only after the replacement is fully constructed.
		select.hidden = true;
	}

	function init() {
		enhance( document.getElementById( 'hj-care-surgery' ) );
		enhance( document.getElementById( 'hj-care-stage' ) );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
