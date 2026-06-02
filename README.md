# Frame Watermark — Ramalho

A WordPress plugin that automatically applies **watermark** or **frame** overlays to images uploaded to posts, using PHP GD for compositing and Advanced Custom Fields for per-post configuration.

## How it works

When a logged-in user uploads a photo to a post gallery on the front end, the plugin hooks into `wp_generate_attachment_metadata` and composites the image using one of two modes, chosen via ACF fields on the parent post:

| Mode | Behaviour |
|------|-----------|
| **Watermark** (`marca_dagua`) | Overlays a PNG watermark on top of the uploaded photo at position (0, 0), preserving transparency. |
| **Frame** (`moldura`) | Places the uploaded photo inside a decorative frame image at offset (76 px, 76 px). The frame becomes the background canvas. |

Processing is skipped for images that don't meet the minimum resolution (1080 × 1920 px). A `_mw_processed` post-meta flag prevents double-processing on metadata regenerations.

## Features

- **Automatic processing** — triggered on upload via `wp_generate_attachment_metadata`; no manual intervention needed.
- **Two compositing modes** — watermark overlay or photo-inside-frame.
- **PNG alpha transparency** — uses `imagecopy()` for PNG overlays to preserve the alpha channel; falls back to `imagecopymerge()` for JPEGs.
- **Front-end gallery upload** — AJAX handlers let logged-in users upload and request removal of gallery images without leaving the page.
- **Per-post configuration** — overlay image and mode are set via ACF fields directly on each post.
- **Idempotent** — the `_mw_processed` meta flag ensures each attachment is processed only once.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.1+ (GD extension required) |
| WordPress | 6.5+ |
| Advanced Custom Fields | Any recent version |

## Installation

1. Clone or download this repository into your `wp-content/plugins/` directory.
2. Ensure the **Advanced Custom Fields** plugin is active.
3. Activate **Frame Watermark — Ramalho** from the WordPress admin.
4. Configure the ACF fields on each post (see [Configuration](#configuration)).

## Configuration

Each post that should have image processing needs two ACF fields:

| Field slug | Type | Description |
|------------|------|-------------|
| `tipo_imagem` | Select | Processing mode: `marca_dagua` (watermark) or `moldura` (frame) |
| `imagem_overlay` | Image | The watermark or frame image to composite onto uploaded photos |

The overlay/frame image should be a PNG with transparency for best results. For frame mode, the frame image dimensions should match the expected output size (1080 × 1920 px by default).

## Image Size

The plugin registers a custom WordPress image size `ft-vertical` (1080 × 1920 px, hard-crop). Uploaded images are processed at this size when available; otherwise the original file is used, provided it meets the minimum resolution.

## Project structure

```
frame-watermark-ramalho/
├── frame-watermark-ramalho.php   # Plugin bootstrap — constants, hooks
├── includes/
│   ├── class-plugin.php          # FWR_Plugin — orchestrates all classes
│   ├── class-image-processor.php # FWR_Image_Processor — GD compositing
│   ├── class-upload-handler.php  # FWR_Upload_Handler — AJAX upload/removal
│   ├── class-acf-fields.php      # FWR_Acf_Fields — registers ACF field groups
│   └── assets/
│       ├── upload.js             # Front-end upload UI
│       └── upload.css            # Front-end upload styles
```

## Development

```bash
# PHP lint
php -l frame-watermark-ramalho.php

# Run tests (requires wp-env or equivalent)
composer run test
```

## License

GPL v2 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

## Author

**Raimundo Ramalho** — [github.com/rairamalho](https://github.com/rairamalho)
