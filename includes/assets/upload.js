/* global PMD_UPLOAD, jQuery */
( function ( $ ) {
	'use strict';

	// Guard: this script depends on PMD_UPLOAD being localised by wp_localize_script.
	// If it is missing (e.g. script loaded outside a singular post page), bail early.
	if ( typeof PMD_UPLOAD === 'undefined' ) {
		return;
	}

	var ajaxUrl  = PMD_UPLOAD.ajax;
	var nonce    = PMD_UPLOAD.nonce;
	var postId   = PMD_UPLOAD.post_id;

	/**
	 * Submits a file upload via AJAX to the pmd_upload_imagem action.
	 *
	 * @param {File}     file   The image file to upload.
	 * @param {Function} onDone Callback invoked with the server response data on success.
	 * @param {Function} onFail Callback invoked with the error message on failure.
	 */
	function uploadImage( file, onDone, onFail ) {
		var data = new FormData();
		data.append( 'action', 'pmd_upload_imagem' );
		data.append( 'nonce', nonce );
		data.append( 'post_id', postId );
		data.append( 'file', file );

		$.ajax( {
			url: ajaxUrl,
			type: 'POST',
			data: data,
			processData: false,
			contentType: false,
			success: function ( res ) {
				if ( res.success ) {
					onDone( res.data );
				} else {
					onFail( res.data );
				}
			},
			error: function () {
				onFail( 'Server error.' );
			},
		} );
	}

	/**
	 * Requests removal of a gallery image by its repeater row index.
	 *
	 * @param {number}   index  Zero-based repeater row index.
	 * @param {Function} onDone Callback on success.
	 * @param {Function} onFail Callback on failure.
	 */
	function requestRemoval( index, onDone, onFail ) {
		$.post( ajaxUrl, {
			action: 'pmd_pedir_remocao',
			nonce: nonce,
			post_id: postId,
			index: index,
		} )
			.done( function ( res ) {
				if ( res.success ) {
					onDone();
				} else {
					onFail( res.data );
				}
			} )
			.fail( function () {
				onFail( 'Server error.' );
			} );
	}

	// Bind a file input with id="pmd-file-input" if present on the page.
	$( document ).on( 'change', '#pmd-file-input', function () {
		var file = this.files[ 0 ];
		if ( ! file ) {
			return;
		}
		uploadImage(
			file,
			function ( data ) {
				// data.url contains the medium-size thumbnail URL.
				console.log( 'Upload OK', data );
			},
			function ( msg ) {
				console.error( 'Upload error:', msg );
			}
		);
	} );

	// Bind removal buttons: <button data-pmd-remove data-index="N">Remove</button>
	$( document ).on( 'click', '[data-pmd-remove]', function () {
		var index = $( this ).data( 'index' );
		requestRemoval(
			index,
			function () {
				console.log( 'Removal requested.' );
			},
			function ( msg ) {
				console.error( 'Removal error:', msg );
			}
		);
	} );

} )( jQuery );
