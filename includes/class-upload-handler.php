<?php
/**
 * AJAX upload handler for the gallery repeater field.
 *
 * Registers front-end assets and AJAX action handlers for image upload
 * and removal-request operations on the gallery ACF repeater field.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FWR_Upload_Handler
 *
 * Hooks into wp_ajax_* actions to handle image uploads from the front end
 * and to flag gallery images for removal.
 */
class FWR_Upload_Handler {

	/**
	 * Registers WordPress hooks in the constructor so a single instantiation
	 * is enough to activate the handler.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts',         array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_pmd_upload_imagem',  array( $this, 'ajax_upload' ) );
		add_action( 'wp_ajax_pmd_pedir_remocao',  array( $this, 'ajax_request_removal' ) );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'rename_upload_file' ) );
	}

	// -------------------------------------------------------------------------
	// Front-end assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueues the upload script and stylesheet on singular post pages.
	 *
	 * Localises the script with the AJAX URL, a nonce, and the current post ID.
	 */
	public function enqueue_assets(): void {
		if ( ! is_singular( FWR_Plugin::POST_TYPE ) ) {
			return;
		}

		wp_enqueue_script(
			'fwr-upload',
			FWR_PLUGIN_URL . 'includes/assets/upload.js',
			array( 'jquery' ),
			'1.1.0',
			true
		);

		wp_enqueue_style(
			'fwr-upload',
			FWR_PLUGIN_URL . 'includes/assets/upload.css',
			array(),
			'1.1.0'
		);

		wp_localize_script(
			'fwr-upload',
			'PMD_UPLOAD',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pmd_upload_imagem' ),
				'post_id' => get_the_ID(),
			)
		);
	}

	// -------------------------------------------------------------------------
	// AJAX: image upload
	// -------------------------------------------------------------------------

	/**
	 * Handles image upload via AJAX.
	 *
	 * Validates nonce, login status, post edit capability, and image dimensions
	 * before uploading the file and appending it to the gallery repeater field.
	 */
	public function ajax_upload(): void {
		check_ajax_referer( 'pmd_upload_imagem', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'Unauthorized.', 'frame-watermark' ) );
		}

		$post_id = absint( $_POST['post_id'] ?? 0 );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied.', 'frame-watermark' ) );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( __( 'No file received.', 'frame-watermark' ) );
		}

		$image_info = getimagesize( $_FILES['file']['tmp_name'] );

		if ( ! $image_info ) {
			wp_send_json_error( __( 'Invalid image file.', 'frame-watermark' ) );
		}

		if ( $image_info[0] < FWR_IMG_WIDTH || $image_info[1] < FWR_IMG_HEIGHT ) {
			wp_send_json_error(
				sprintf(
					/* translators: 1: minimum width, 2: minimum height */
					__( 'Image must be at least %1$dx%2$d pixels.', 'frame-watermark' ),
					FWR_IMG_WIDTH,
					FWR_IMG_HEIGHT
				)
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload( 'file', $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( $attachment_id->get_error_message() );
		}

		// Assign the attachment to the current user so authorship is correct.
		wp_update_post( array(
			'ID'          => $attachment_id,
			'post_author' => get_current_user_id(),
		) );

		// Append the new image to the gallery repeater field.
		$gallery = get_field( 'galeria_imagens', $post_id );

		if ( ! is_array( $gallery ) ) {
			$gallery = array();
		}

		$gallery[] = array(
			'imagem_galeria' => $attachment_id,
			'aprovado'       => 0,
		);

		update_field( 'galeria_imagens', $gallery, $post_id );

		wp_send_json_success( array(
			'id'  => $attachment_id,
			'url' => wp_get_attachment_image_url( $attachment_id, 'medium' ),
		) );
	}

	// -------------------------------------------------------------------------
	// AJAX: removal request
	// -------------------------------------------------------------------------

	/**
	 * Flags a gallery image as removal-requested via AJAX.
	 *
	 * Only users with the edit_post capability on the target post may request removal.
	 *
	 * Note: the registered action hook key remains pmd_pedir_remocao to preserve
	 * compatibility with the JS AJAX call and stored ACF field keys.
	 */
	public function ajax_request_removal(): void {
		check_ajax_referer( 'pmd_pedir_remocao_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'Unauthorized.', 'frame-watermark' ) );
		}

		$post_id = absint( $_POST['post_id'] ?? 0 );
		$index   = absint( $_POST['index'] ?? 0 );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Permission denied.', 'frame-watermark' ) );
		}

		$gallery = get_field( 'galeria_imagens', $post_id );

		if ( ! is_array( $gallery ) || ! isset( $gallery[ $index ] ) ) {
			wp_send_json_error( __( 'Image not found.', 'frame-watermark' ) );
		}

		$gallery[ $index ]['pedido_remocao'] = 1;
		update_field( 'galeria_imagens', $gallery, $post_id );

		wp_send_json_success( true );
	}

	// -------------------------------------------------------------------------
	// Upload filter
	// -------------------------------------------------------------------------

	/**
	 * Renames uploaded files before WordPress saves them to disk.
	 *
	 * Generates a sanitised, collision-resistant filename using the original
	 * basename, a Unix timestamp, and an 8-character random token.
	 *
	 * @param array $file The file array from $_FILES, passed by filter reference.
	 * @return array Modified file array with the new filename.
	 */
	public function rename_upload_file( array $file ): array {
		$extension = pathinfo( $file['name'], PATHINFO_EXTENSION );
		$basename  = pathinfo( $file['name'], PATHINFO_FILENAME );
		$unique    = sanitize_file_name( $basename ) . '-' . time() . '-' . wp_generate_password( 8, false );

		$file['name'] = $unique . '.' . $extension;

		return $file;
	}
}
