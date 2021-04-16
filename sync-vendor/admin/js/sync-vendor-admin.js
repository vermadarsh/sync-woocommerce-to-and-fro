jQuery( document ).ready(function( $ ) {
	'use strict';

	// Check for read log settings section.
	if ( 'read-log' === SVN_Admin_JS_Obj.section ) {
		$( '<button type="button" class="button svn-delete-log">' + SVN_Admin_JS_Obj.delete_log_button_text + '</button>' ).insertAfter( '#svn_sync_log_input' );

		// Remove the save settings button.
		$( '.woocommerce-save-button' ).remove();

		/**
		 * Delete the sync log.
		 */
		$( document ).on( 'click', '.svn-delete-log', function() {
			var delete_log_cnf = confirm( SVN_Admin_JS_Obj.delete_log_confirmation );
			var this_button    = $( this );

			if ( false === delete_log_cnf ) {
				return;
			}

			this_button.text( 'Please wait...' ).addClass( 'non-clickable' );

			var data = {
				action: 'svn_delete_log',
			};
			$.ajax( {
				dataType: 'JSON',
				url: SVN_Admin_JS_Obj.ajaxurl,
				type: 'POST',
				data: data,
				success: function ( response ) {

					if ( 'svn-sync-log-deleted' === response.data.code ) {
						$( '#svn_sync_log_input' ).val( '' );
						this_button.text( 'Log Deleted !!' ).removeClass( 'non-clickable' );
					}
				},
			} );
		} );
	}
});
