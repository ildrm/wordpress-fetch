( function ( blocks, element, blockEditor, i18n ) {
	'use strict';
	blocks.registerBlockType( 'wordpress-fetch/source-attribution', {
		edit: function () {
			return element.createElement( 'div', blockEditor.useBlockProps( { className: 'wpfetch-attribution-preview' } ), i18n.__( 'The original article link will appear here.', 'wordpress-fetch' ) );
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n );
