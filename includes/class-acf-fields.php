<?php
/**
 * ACF field group registration for the Frame Watermark plugin.
 *
 * Registers the Image Configuration field group on the post edit screen.
 * Depends on Advanced Custom Fields (ACF) being active.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class FWR_ACF_Fields
 *
 * Defines and registers the ACF local field group used by the plugin.
 */
class FWR_ACF_Fields {

	/**
	 * Registers the field group via the acf/init hook.
	 *
	 * Bails early if ACF is not active.
	 */
	public static function register(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group( array(
			'key'    => 'group_pmd_config_imagem',
			'title'  => 'Image Configuration (Frame Watermark)',
			'fields' => array(

				// --- Image type selector ---
				array(
					'key'           => 'field_pmd_tipo_imagem',
					'label'         => 'Image Type',
					'name'          => 'tipo_imagem',
					'type'          => 'select',
					'choices'       => array(
						'original'    => 'Original',
						'marca_dagua' => 'With Watermark',
						'moldura'     => 'With Frame',
					),
					'default_value' => 'original',
					'ui'            => 1,
				),

				// --- Overlay/frame image (shown conditionally based on image type) ---
				array(
					'key'               => 'field_pmd_imagem_overlay',
					'label'             => 'Overlay Image',
					'name'              => 'imagem_overlay',
					'type'              => 'image',
					'return_format'     => 'id',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_pmd_tipo_imagem',
								'operator' => '==',
								'value'    => 'marca_dagua',
							),
						),
						array(
							array(
								'field'    => 'field_pmd_tipo_imagem',
								'operator' => '==',
								'value'    => 'moldura',
							),
						),
					),
				),

				// --- Gallery repeater ---
				array(
					'key'          => 'field_pmd_galeria_imagens',
					'label'        => 'Image Gallery',
					'name'         => 'galeria_imagens',
					'type'         => 'repeater',
					'layout'       => 'row',
					'button_label' => 'Add image',
					'sub_fields'   => array(

						array(
							'key'           => 'field_pmd_galeria_imagem',
							'label'         => 'Gallery Image',
							'name'          => 'imagem_galeria',
							'type'          => 'image',
							'return_format' => 'id',
							'preview_size'  => 'thumbnail',
							'library'       => 'all',
							'required'      => 1,
						),

						array(
							'key'           => 'field_pmd_galeria_aprovado',
							'label'         => 'Approved',
							'name'          => 'aprovado',
							'type'          => 'true_false',
							'ui'            => 1,
							'default_value' => 0,
							'message'       => 'Image approved for display',
						),

						array(
							'key'           => 'field_pmd_galeria_pedido_remocao',
							'label'         => 'Removal Requested',
							'name'          => 'pedido_remocao',
							'type'          => 'true_false',
							'ui'            => 1,
							'default_value' => 0,
							'message'       => 'User requested removal of this image',
						),

					),
				),

			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => FWR_Plugin::POST_TYPE,
					),
				),
			),
		) );
	}
}
