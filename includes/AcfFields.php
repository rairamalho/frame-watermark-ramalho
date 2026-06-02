<?php

namespace PMD;

use MW_Plugin;

class AcfFields
{
  public static function register()
  {
    if (!function_exists('acf_add_local_field_group')) {
      return;
    }

    acf_add_local_field_group([
      'key' => 'group_pmd_config_imagem',
      'title' => 'Configuração de Imagem (Marca d’Água)',
      'fields' => [

        [
          'key' => 'field_pmd_tipo_imagem',
          'label' => 'Tipo de Imagem',
          'name' => 'tipo_imagem',
          'type' => 'select',
          'choices' => [
            'original'     => 'Original',
            'marca_dagua'  => 'Com Marca d’Água',
            'moldura'      => 'Com Moldura',
          ],
          'default_value' => 'original',
          'ui' => 1,
        ],

        [
          'key' => 'field_pmd_imagem_overlay',
          'label' => 'Imagem Overlay',
          'name' => 'imagem_overlay',
          'type' => 'image',
          'return_format' => 'id',
          'conditional_logic' => [
            [
              [
                'field' => 'field_pmd_tipo_imagem',
                'operator' => '==',
                'value' => 'marca_dagua',
              ],
            ],
            [
              [
                'field' => 'field_pmd_tipo_imagem',
                'operator' => '==',
                'value' => 'moldura',
              ],
            ],
          ],
        ],

        // 🔹 REPEATER DA GALERIA
        [
          'key' => 'field_pmd_galeria_imagens',
          'label' => 'Galeria de Imagens',
          'name' => 'galeria_imagens',
          'type' => 'repeater',
          'layout' => 'row',
          'button_label' => 'Adicionar imagem',
          'sub_fields' => [

            [
              'key' => 'field_pmd_galeria_imagem',
              'label' => 'Imagem para Galeria',
              'name' => 'imagem_galeria',
              'type' => 'image',
              'return_format' => 'id',
              'preview_size' => 'thumbnail',
              'library' => 'all',
              'required' => 1,
            ],

            [
              'key' => 'field_pmd_galeria_aprovado',
              'label' => 'Aprovado',
              'name' => 'aprovado',
              'type' => 'true_false',
              'ui' => 1,
              'default_value' => 0,
              'message' => 'Imagem aprovada para exibição',
            ],

            [
              'key' => 'field_pmd_galeria_pedido_remocao',
              'label' => 'Pedido para remover',
              'name' => 'pedido_remocao',
              'type' => 'true_false',
              'ui' => 1,
              'default_value' => 0,
              'message' => 'Usuário solicitou a remoção desta imagem',
            ],

          ],
        ],

      ],
      'location' => [
        [
          [
            'param' => 'post_type',
            'operator' => '==',
            'value' => MW_Plugin::POST_TYPE,
          ],
        ],
      ],
    ]);
  }
}
