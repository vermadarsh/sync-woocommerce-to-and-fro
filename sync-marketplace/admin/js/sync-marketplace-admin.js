jQuery( document ).ready(function( $ ) {
	'use strict';

	// Check for read log settings section.
	if ( 'read-log' === SMP_Admin_JS_Obj.section ) {
		$( '<button type="button" class="button smp-delete-log">' + SMP_Admin_JS_Obj.delete_log_button_text + '</button>' ).insertAfter( '#smp_sync_log_input' );

		// Remove the save settings button.
		$( '.woocommerce-save-button' ).remove();

		/**
		 * Delete the sync log.
		 */
		$( document ).on( 'click', '.smp-delete-log', function() {
			var delete_log_cnf = confirm( SMP_Admin_JS_Obj.delete_log_confirmation );
			var this_button    = $( this );

			if ( false === delete_log_cnf ) {
				return;
			}

			this_button.text( 'Please wait...' ).addClass( 'non-clickable' );

			var data = {
				action: 'smp_delete_log',
			};
			$.ajax( {
				dataType: 'JSON',
				url: SMP_Admin_JS_Obj.ajaxurl,
				type: 'POST',
				data: data,
				success: function ( response ) {

					if ( 'smp-sync-log-deleted' === response.data.code ) {
						$( '#smp_sync_log_input' ).val( '' );
						this_button.text( 'Log Deleted !!' ).removeClass( 'non-clickable' );
					}
				},
			} );
		} );
	}

	// Check for rest-api settings section.
	if ( 'rest-api' === SMP_Admin_JS_Obj.section ) {
		$( '<input type="submit" class="button smp-verify-rest-api-credentials non-clickable" name="smp-verify-rest-api-credentials" value="' + SMP_Admin_JS_Obj.verify_creds_button_text + '">' ).insertBefore( '.woocommerce-save-button' );
	}
} );
