<?php
/**
 * Image processing class for the Frame Watermark plugin.
 *
 * Applies watermark or frame overlays to uploaded images using the PHP GD library.
 * Triggered automatically via the wp_generate_attachment_metadata filter.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FWR_Image_Processor
 *
 * Handles composite image operations (watermark, frame) for post attachments.
 */
class FWR_Image_Processor {

	/** Minimum required image width in pixels. */
	const WIDTH = 1080;

	/** Minimum required image height in pixels. */
	const HEIGHT = 1920;

	/** Registered WordPress image size name used by this plugin. */
	const SIZE_NAME = 'ft-vertical';

	/** @var int WordPress attachment post ID. */
	protected int $attachment_id;

	/** @var int Parent post ID (the post the attachment belongs to). */
	protected int $post_id;

	/** @var string Absolute filesystem path to the source image. */
	protected string $source_path;

	/** @var string|null Absolute path to the processed output image, or null if processing failed. */
	protected ?string $final_path = null;

	// -------------------------------------------------------------------------
	// Entry point
	// -------------------------------------------------------------------------

	/**
	 * Filter callback for wp_generate_attachment_metadata.
	 *
	 * Processes the ft-vertical image size when it exists, otherwise falls back
	 * to the original file, provided it meets the minimum dimension requirements.
	 * Sets the _mw_processed post meta flag to prevent double-processing.
	 *
	 * @param array $metadata      Attachment metadata array.
	 * @param int   $attachment_id Attachment post ID.
	 * @return array Possibly modified metadata.
	 */
	public static function process_ft_vertical( array $metadata, int $attachment_id ): array {
		// Prevent reprocessing on subsequent metadata regenerations.
		if ( get_post_meta( $attachment_id, '_mw_processed', true ) ) {
			return $metadata;
		}

		$upload_dir    = wp_upload_dir();
		$original_path = trailingslashit( $upload_dir['basedir'] ) . $metadata['file'];
		$has_ft_size   = ! empty( $metadata['sizes'][ self::SIZE_NAME ] );

		if ( $has_ft_size ) {
			$size   = $metadata['sizes'][ self::SIZE_NAME ];
			$source = trailingslashit( $upload_dir['basedir'] )
				. dirname( $metadata['file'] ) . '/'
				. $size['file'];
		} else {
			$source = $original_path;
		}

		if ( ! file_exists( $source ) ) {
			return $metadata;
		}

		// Reject images smaller than the minimum required dimensions.
		$image_info = getimagesize( $source );
		if ( ! $image_info || $image_info[0] < self::WIDTH || $image_info[1] < self::HEIGHT ) {
			return $metadata;
		}

		$processor = new self( $attachment_id, $source );
		$processor->process();

		if ( ! $processor->final_path ) {
			return $metadata;
		}

		// Copy the processed file back over the original so WordPress serves
		// the composited version at its canonical URL.
		if ( ! copy( $processor->final_path, $original_path ) ) {
			error_log( sprintf( '[FWR] Failed to copy processed image to %s', $original_path ) );
			return $metadata;
		}

		if ( $has_ft_size ) {
			$metadata['sizes'][ self::SIZE_NAME ]['file'] = basename( $processor->final_path );
		}

		update_post_meta( $attachment_id, '_mw_processed', true );

		return $metadata;
	}

	// -------------------------------------------------------------------------
	// Constructor
	// -------------------------------------------------------------------------

	/**
	 * @param int    $attachment_id WordPress attachment ID.
	 * @param string $source_path   Absolute path to the image to process.
	 */
	public function __construct( int $attachment_id, string $source_path ) {
		$this->attachment_id = $attachment_id;
		$this->post_id       = (int) wp_get_post_parent_id( $attachment_id );
		$this->source_path   = $source_path;
	}

	// -------------------------------------------------------------------------
	// Processing pipeline
	// -------------------------------------------------------------------------

	/**
	 * Reads ACF field values and dispatches to the appropriate compositing method.
	 *
	 * Bails silently when there is no parent post or required ACF fields are empty.
	 */
	protected function process(): void {
		if ( ! $this->post_id ) {
			return;
		}

		$image_type = get_field( 'tipo_imagem', $this->post_id );
		$overlay_id = get_field( 'imagem_overlay', $this->post_id );

		if ( ! $image_type || ! $overlay_id ) {
			return;
		}

		if ( 'marca_dagua' === $image_type ) {
			$this->apply_overlay( (int) $overlay_id, 'watermark' );
		}

		if ( 'moldura' === $image_type ) {
			$this->apply_frame( (int) $overlay_id );
		}
	}

	// -------------------------------------------------------------------------
	// Compositing strategies
	// -------------------------------------------------------------------------

	/**
	 * Overlays a watermark image on top of the source image at position (0, 0).
	 *
	 * @param int    $overlay_id Attachment ID of the watermark image.
	 * @param string $suffix     Filename suffix used when generating the output path.
	 */
	protected function apply_overlay( int $overlay_id, string $suffix ): void {
		$overlay_path = get_attached_file( $overlay_id );

		if ( ! $overlay_path || ! file_exists( $overlay_path ) ) {
			error_log( sprintf( '[FWR] Overlay file not found for attachment %d', $overlay_id ) );
			return;
		}

		$new_path = $this->generate_new_path( $suffix );
		copy( $this->source_path, $new_path );

		$this->composite_images( $new_path, $overlay_path, 0, 0, 100 );

		$this->final_path = $new_path;
	}

	/**
	 * Composites the source image on top of a frame image at position (76, 76).
	 *
	 * The frame image is the background canvas; the source image is placed inside it.
	 *
	 * @param int $frame_id Attachment ID of the frame image.
	 */
	protected function apply_frame( int $frame_id ): void {
		$frame_path = get_attached_file( $frame_id );

		if ( ! $frame_path || ! file_exists( $frame_path ) ) {
			error_log( sprintf( '[FWR] Frame file not found for attachment %d', $frame_id ) );
			return;
		}

		$new_path = $this->generate_new_path( 'frame' );

		// The frame image is the background canvas; copy it first.
		copy( $frame_path, $new_path );

		// Place the source image inside the frame at the defined offset.
		$this->composite_images( $new_path, $this->source_path, 76, 76, 100 );

		$this->final_path = $new_path;
	}

	// -------------------------------------------------------------------------
	// GD compositing
	// -------------------------------------------------------------------------

	/**
	 * Composites $overlay_path on top of $base_path using the PHP GD library.
	 *
	 * Preserves PNG alpha transparency. For PNG overlays at full opacity,
	 * uses imagecopy() which respects the alpha channel. For JPEGs or partial
	 * opacity, falls back to imagecopymerge() which discards the alpha channel.
	 *
	 * @param string $base_path    Absolute path to the base (destination) image. Modified in-place.
	 * @param string $overlay_path Absolute path to the image to composite on top.
	 * @param int    $x            Horizontal destination offset in pixels.
	 * @param int    $y            Vertical destination offset in pixels.
	 * @param int    $opacity      Opacity percentage (0–100). Only respected for non-PNG overlays.
	 */
	protected function composite_images(
		string $base_path,
		string $overlay_path,
		int $x,
		int $y,
		int $opacity = 100
	): void {
		$base_data    = file_get_contents( $base_path );
		$overlay_data = file_get_contents( $overlay_path );

		if ( false === $base_data || false === $overlay_data ) {
			error_log( sprintf( '[FWR] Failed to read image data from %s or %s', $base_path, $overlay_path ) );
			return;
		}

		$base    = imagecreatefromstring( $base_data );
		$overlay = imagecreatefromstring( $overlay_data );

		if ( ! $base || ! $overlay ) {
			error_log( sprintf( '[FWR] GD could not decode image: %s / %s', $base_path, $overlay_path ) );
			return;
		}

		// Ensure the base canvas supports full alpha transparency.
		imagealphablending( $base, true );
		imagesavealpha( $base, true );

		// Ensure the overlay retains its alpha channel during compositing.
		imagealphablending( $overlay, true );
		imagesavealpha( $overlay, true );

		$is_png_overlay = 'png' === strtolower( pathinfo( $overlay_path, PATHINFO_EXTENSION ) );

		if ( $is_png_overlay && 100 === $opacity ) {
			// imagecopy() preserves real PNG alpha transparency.
			imagecopy( $base, $overlay, $x, $y, 0, 0, imagesx( $overlay ), imagesy( $overlay ) );
		} else {
			// imagecopymerge() blends by percentage but ignores the alpha channel.
			imagecopymerge( $base, $overlay, $x, $y, 0, 0, imagesx( $overlay ), imagesy( $overlay ), $opacity );
		}

		$ext = strtolower( pathinfo( $base_path, PATHINFO_EXTENSION ) );

		if ( 'png' === $ext ) {
			imagepng( $base, $base_path );
		} else {
			imagejpeg( $base, $base_path, 90 );
		}

		// GdImage objects are garbage-collected automatically in PHP 8.1+;
		// imagedestroy() is deprecated and omitted intentionally.
		unset( $base, $overlay );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Generates a unique output file path by appending a suffix and a uniqid token.
	 *
	 * Example output: /uploads/2025/06/photo-watermark-6642a1b3e4c5f.jpg
	 *
	 * @param string $suffix Label describing the processing type (e.g. 'watermark', 'frame').
	 * @return string Absolute filesystem path for the processed output file.
	 */
	protected function generate_new_path( string $suffix ): string {
		$info = pathinfo( $this->source_path );

		return $info['dirname'] . '/'
			. $info['filename'] . '-' . $suffix . '-' . uniqid() . '.'
			. $info['extension'];
	}
}
