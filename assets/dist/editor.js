( function ( plugins, editPost, element, components, data, i18n ) {
	'use strict';
	var el = element.createElement;
	function Panel() {
		var meta = data.useSelect( function ( select ) { return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {}; }, [] );
		var edit = data.useDispatch( 'core/editor' ).editPost;
		var fields = [ 'title', 'content', 'excerpt', 'featured_image', 'taxonomies', 'seo' ];
		return el( editPost.PluginDocumentSettingPanel, { name: 'wpfetch-sync', title: i18n.__( 'Imported content', 'wordpress-fetch' ), className: 'wpfetch-editor-panel' },
			el( components.ToggleControl, { label: i18n.__( 'Lock all synchronization', 'wordpress-fetch' ), checked: !! meta._wpfetch_sync_locked, onChange: function ( value ) { edit( { meta: Object.assign( {}, meta, { _wpfetch_sync_locked: value } ) } ); } } ),
			el( 'p', {}, i18n.__( 'Protect selected fields from future source updates:', 'wordpress-fetch' ) ),
			fields.map( function ( field ) { var selected = meta._wpfetch_protected_fields || []; return el( components.CheckboxControl, { key: field, label: field.replace( '_', ' ' ), checked: selected.indexOf( field ) !== -1, onChange: function ( checked ) { var next = checked ? selected.concat( [ field ] ) : selected.filter( function ( value ) { return value !== field; } ); edit( { meta: Object.assign( {}, meta, { _wpfetch_protected_fields: next } ) } ); } } ); } )
		);
	}
	plugins.registerPlugin( 'wordpress-fetch-editor', { render: Panel, icon: 'rss' } );
} )( window.wp.plugins, window.wp.editPost, window.wp.element, window.wp.components, window.wp.data, window.wp.i18n );
