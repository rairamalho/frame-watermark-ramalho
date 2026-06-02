<?php

if (!defined('ABSPATH')) exit;

class Image_Processor
{
    protected int $attachment_id;
    protected int $post_id;
    protected string $source_path;
    protected ?string $final_path = null;

    const WIDTH  = 1080;
    const HEIGHT = 1920;
    const SIZE_NAME = 'ft-vertical';

    /* =====================================================
     * ENTRY POINT — chamado pelo wp_generate_attachment_metadata
     * ===================================================== */
    public static function process_ft_vertical(array $metadata, int $attachment_id): array
    {
        // Já processado
        if (get_post_meta($attachment_id, '_mw_processed', true)) {
            return $metadata;
        }

        // Size não existe
        if (empty($metadata['sizes'][self::SIZE_NAME])) {
            // Aplicar aqui a moldura mesmo sem o ft-vertical, usando a imagem original
            $upload_dir = wp_upload_dir();
            $original_path = trailingslashit($upload_dir['basedir']) . $metadata['file'];
            if (!file_exists($original_path)) {
                return $metadata;
            }

            // testar para ver se a imagem original tem pelo menos 1080x1920, caso contrário não processar
            $image_info = getimagesize($original_path);
            if ($image_info[0] < self::WIDTH || $image_info[1] < self::HEIGHT) {
                return $metadata;
            }

            $processor = new self($attachment_id, $original_path);
            $processor->process();
            if ($processor->final_path) {
                // Atualiza o arquivo original
                copy($processor->final_path, $original_path);
                
                update_post_meta($attachment_id, '_mw_processed', true);
            }


            return $metadata;
        }else{
            $upload_dir = wp_upload_dir();
            $size = $metadata['sizes'][self::SIZE_NAME];

            $ft_path = trailingslashit($upload_dir['basedir']) .
                dirname($metadata['file']) . '/' .
                $size['file'];

            if (!file_exists($ft_path)) {
                return $metadata;
            }

            $processor = new self($attachment_id, $ft_path);
            $processor->process();

            if ($processor->final_path) {
                // Atualiza o size ft-vertical
                $metadata['sizes'][self::SIZE_NAME]['file'] = basename($processor->final_path);
                
                // Atualiza o arquivo original
                $upload_dir = wp_upload_dir();
                $original_path = trailingslashit($upload_dir['basedir']) . $metadata['file'];
                copy($processor->final_path, $original_path);
                
                update_post_meta($attachment_id, '_mw_processed', true);
            }


            return $metadata;
        }
    }

    /* =====================================================
     * CONSTRUTOR
     * ===================================================== */
    public function __construct(int $attachment_id, string $source_path)
    {
        $this->attachment_id = $attachment_id;
        $this->post_id = wp_get_post_parent_id($attachment_id);
        $this->source_path = $source_path;
    }

    /* =====================================================
     * PROCESSAMENTO PRINCIPAL
     * ===================================================== */
    protected function process(): void
    {
        if (!$this->post_id) return;

        $tipo = get_field('tipo_imagem', $this->post_id);
        $overlay_id = get_field('imagem_overlay', $this->post_id);

        if (!$tipo || !$overlay_id) return;

        if ($tipo === 'marca_dagua') {
            $this->apply_overlay($overlay_id, 'watermark');
        }

        if ($tipo === 'moldura') {
            $this->apply_frame($overlay_id);
        }
    }

    /* =====================================================
     * WATERMARK
     * ===================================================== */
    protected function apply_overlay(int $overlay_id, string $suffix): void
    {
        $overlay_path = get_attached_file($overlay_id);
        if (!file_exists($overlay_path)) return;

        $new_path = $this->generate_new_path($suffix);
        copy($this->source_path, $new_path);

        $this->composite_images(
            $new_path,
            $overlay_path,
            0,
            0,
            100
        );

        $this->final_path = $new_path;
    }

    /* =====================================================
     * FRAME
     * ===================================================== */
    protected function apply_frame(int $frame_id): void
    {
        $frame_path = get_attached_file($frame_id);
        if (!file_exists($frame_path)) return;

        $new_path = $this->generate_new_path('frame');

        // Copia moldura como base
        copy($frame_path, $new_path);

        // Sobrepõe a imagem ft-vertical
        $this->composite_images(
            $new_path,
            $this->source_path,
            76,
            76,
            100
        );

        $this->final_path = $new_path;
    }

    /* =====================================================
     * COMPOSITE (preserva PNG alpha)
     * ===================================================== */
    protected function composite_images(
        string $base_path,
        string $overlay_path,
        int $x,
        int $y,
        int $opacity = 100
    ): void {

        $base = imagecreatefromstring(file_get_contents($base_path));
        $overlay = imagecreatefromstring(file_get_contents($overlay_path));

        if (!$base || !$overlay) return;

        // Garante alpha no base
        imagealphablending($base, true);
        imagesavealpha($base, true);

        // Garante alpha no overlay
        imagealphablending($overlay, true);
        imagesavealpha($overlay, true);

        $is_png_overlay = strtolower(pathinfo($overlay_path, PATHINFO_EXTENSION)) === 'png';

        if ($is_png_overlay && $opacity === 100) {
            // PNG com transparência REAL
            imagecopy(
                $base,
                $overlay,
                $x,
                $y,
                0,
                0,
                imagesx($overlay),
                imagesy($overlay)
            );
        } else {
            // JPG ou PNG com opacidade manual
            imagecopymerge(
                $base,
                $overlay,
                $x,
                $y,
                0,
                0,
                imagesx($overlay),
                imagesy($overlay),
                $opacity
            );
        }

        $ext = strtolower(pathinfo($base_path, PATHINFO_EXTENSION));

        if ($ext === 'png') {
            imagepng($base, $base_path);
        } else {
            imagejpeg($base, $base_path, 90);
        }

        imagedestroy($base);
        imagedestroy($overlay);
    }

    /* =====================================================
     * PATH
     * ===================================================== */
    protected function generate_new_path(string $suffix): string
    {
        $info = pathinfo($this->source_path);

        return $info['dirname'] . '/' .
            $info['filename'] . '-' . $suffix . '-' . uniqid() . '.' .
            $info['extension'];
    }
}
