<?php
/**
 * Plugin Name: MW Upload Galeria AJAX
 */

if (!defined('ABSPATH')) exit;

class Upload_Galeria
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('wp_ajax_pmd_upload_imagem', [$this, 'ajax_upload']);
        add_action('wp_ajax_pmd_pedir_remocao', [$this, 'ajax_pedir_remocao']);
        add_filter('wp_handle_upload_prefilter', [$this, 'rename_upload_file']);
    }

    public function assets()
    {
        if (!is_singular(MW_Plugin::POST_TYPE)) return;

        wp_enqueue_script(
            'pmd-upload',
            plugin_dir_url(__FILE__) . 'assets/upload.js',
            ['jquery'],
            '1.0',
            true
        );

        wp_enqueue_style(
            'pmd-upload',
            plugin_dir_url(__FILE__) . 'assets/upload.css'
        );

        wp_localize_script('pmd-upload', 'PMD_UPLOAD', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pmd_upload_nonce'),
            'post_id' => get_the_ID(),
        ]);
    }

    public function ajax_upload()
    {
        check_ajax_referer('pmd_upload_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Não autorizado');
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error('Arquivo não enviado');
        }

        // Se o arquivo for menor que 1080x1920, rejeitar
        $image_info = getimagesize($_FILES['file']['tmp_name']);
        if ($image_info[0] < MW_Plugin::IMG_WIDTH || $image_info[1] < MW_Plugin::IMG_HEIGHT) {
            wp_send_json_error('A imagem deve ter pelo menos 1080x1920 pixels');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $post_id = intval($_POST['post_id']);
        $current_user = get_current_user_id();

        $attachment_id = media_handle_upload('file', $post_id);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error($attachment_id->get_error_message());
        }

        wp_update_post([
            'ID' => $attachment_id,
            'post_author' => $current_user,
        ]);

        // Lê estado atual do repeater
        $galeria = get_field('galeria_imagens', $post_id);

        // Garante array
        if (!is_array($galeria)) {
            $galeria = [];
        }

        // Adiciona nova linha
        $galeria[] = [
            'imagem_galeria' => $attachment_id,
            'aprovado' => 0,
        ];

        // Salva tudo de uma vez
        update_field('galeria_imagens', $galeria, $post_id);

        wp_send_json_success([
            'id' => $attachment_id,
            'url' => wp_get_attachment_image_url($attachment_id, 'medium')
        ]);
    }

    public function rename_upload_file($file)
    {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $basename = pathinfo($file['name'], PATHINFO_FILENAME);
        
        // Gera nome único com timestamp e hash
        $unique_name = sanitize_file_name($basename) . '-' . time() . '-' . wp_generate_password(8, false);
        $file['name'] = $unique_name . '.' . $extension;
        
        return $file;
    }

    public function ajax_pedir_remocao()
    {
/*
        if (!wp_verify_nonce($_POST['nonce'], 'pmd_pedir_remocao')) {
            wp_send_json_error('Nonce inválido');
        }
        if (!is_user_logged_in()) {
            wp_send_json_error('Não autorizado');
        }
*/
        $post_id = intval($_POST['post_id']);
        $index   = intval($_POST['index']);

        $galeria = get_field('galeria_imagens', $post_id);

        if (!isset($galeria[$index])) {
            wp_send_json_error('Imagem não encontrada');
        }

        $galeria[$index]['pedido_remocao'] = 1;

        update_field('galeria_imagens', $galeria, $post_id);

        wp_send_json_success(true);
    }

}
