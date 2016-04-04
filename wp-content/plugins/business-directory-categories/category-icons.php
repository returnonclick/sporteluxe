<?php

class WPBDP_CategoryIconsModule {

    public static function instance() {
        static $instance = null;

        if ( !$instance ) {
            $instance = new self;
        }

        return $instance;
    }

    private function __construct() {
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'wpbdp_modules_init', array( $this, 'init' ) );
        add_action( 'wpbdp_register_settings', array( $this, 'register_settings' ) );

        add_action( 'wpbdp_enqueue_scripts', array( $this, '_enqueue_scripts' ) );
    }

    public function init() {
        if ( !get_option( 'wpbdp[category_images]', null ) ) {
            update_option( 'wpbdp[category_images]', array( 'images' => array(), 'temp' => array() ) );
        }

        add_filter( 'wpbdp_categories_list_css', array( $this, '_categories_list_css'), 10, 1 );
        add_filter( 'wpbdp_categories_list_item', array( $this, '_categories_list_item'), 10, 2 );
        add_filter( 'wpbdp_categories_list_item_css', array( $this, '_categories_list_item_css'), 10, 2 );

        if ( wpbdp_get_option( 'categories-use-images' ) )
            add_filter( 'wpbdp_categories_list_anidate_children', '__return_false' );
    }

    public function register_settings( &$api ) {
        $s = $api->add_section( 'image', 'category-enhancements-images', __('Category Images', 'wpbdp-categories' ) );
        $api->add_setting( $s, 'categories-use-images', __( 'Display the category list using images', 'wpbdp-categories' ), 'boolean', true );
        $api->add_setting( $s, 'categories-images-width', __( 'Category image width (px)', 'wpbdp-categories' ), 'text', '80' );
        $api->add_setting( $s, 'categories-images-height', __( 'Category image height (px)', 'wpbdp-categories' ), 'text', '80' );
    }

    public function admin_init() {
        add_action('wpbdp_category_edit_form_fields', array($this, '_category_edit_form_fields'));
        add_action('wp_ajax_wpbdp-category-images-upload', array($this, '_upload_image'));
        add_action('admin_head-edit-tags.php', array($this, '_upload_image_scripts'));
        add_action('edited_term', array($this, '_category_update'), 10, 3);

        add_filter( 'manage_edit-wpbdp_category_columns', array( $this, '_admin_category_columns' ) );
        add_filter( 'manage_wpbdp_category_custom_column', array( $this, '_admin_custom_category_column' ), 10, 3 );
    }

    /*
     * Category images.
     */
    private function register_temp_image( $term_id, $image_path ) {
        $images = $this->get_temp_images( $term_id );
        $images[] = array( 'file' => _wp_relative_upload_path( $image_path ) );
        $this->set_temp_images( $term_id, $images );
    }

    private function get_temp_images($term_id) {
        $category_images = get_option('wpbdp[category_images]');

        if ($term_id) {
            return isset($category_images['temp'][$term_id]) ? $category_images['temp'][$term_id] : array();
        } else {
            return isset($category_images['temp']['noterm']) ? $category_images['temp']['noterm'] : array();
        }
    }

    private function set_temp_images($term_id, $images=array()) {
        $category_images = get_option('wpbdp[category_images]');

        if ($term_id) {
            $category_images['temp'][$term_id] = $images;
        } else {
            $category_images['temp']['noterm'] = $images;
        }

        update_option('wpbdp[category_images]', $category_images);
    }

    private function get_term_image($term_id) {
        $upload_dir = wp_upload_dir();
        $category_images = get_option( 'wpbdp[category_images]' );

        if ( ! isset( $category_images['images'][ $term_id ] ) )
            return null;

        $data = $category_images['images'][ $term_id ];

        // Update data to new format. Since 3.6.2.
        if ( ! isset( $data['file'] ) ) {
            if ( $file = _wp_relative_upload_path( $data['path'] ) ) {
                $data['file'] = $file;
            } else {
                $data['file'] = ltrim( str_replace( realpath( $upload_dir['basedir'] ), '', realpath( $data['path'] ) ), '/' );
            }

            unset( $data['url'] );
            unset( $data['path'] );

            $category_images['images'][ $term_id ] = $data;
            update_option('wpbdp[category_images]', $category_images);
        }

        $data['url'] = trailingslashit( $upload_dir['baseurl'] ) . $data['file'];
        $data['path'] = trailingslashit( $upload_dir['basedir'] ) . $data['file'];

        return $data;
    }

    private function set_term_image($term_id, $image_path, $do_cleanup=true) {
        $upload_dir = wp_upload_dir();
        $image_file = _wp_relative_upload_path( $image_path );

        if ( $do_cleanup ) {
            $temp_images = $this->get_temp_images( $term_id );

            if ( $current_image = $this->get_term_image( $term_id ) ) {
                if ( $current_image['file'] != $image_file )
                    $temp_images[] = $current_image;
            }

            foreach ( $temp_images as $img ) {
                if ( $img['file'] != $image_file ) {
                    $path = trailingslashit( $upload_dir['basedir'] ) . $img['file'];

                    if ( $path && file_exists( $path ) )
                        @unlink( realpath( $path ) );
                }
            }

            $this->set_temp_images( $term_id, array() );
        }

        $category_images = get_option('wpbdp[category_images]');

        if ( $image_path && file_exists( $image_path ) ) {
            $category_images['images'][$term_id] = array( 'file' => $image_file );
        } else {
            unset( $category_images['images'][ $term_id ] );
        }

        update_option('wpbdp[category_images]', $category_images);
    }

    public function _admin_category_columns($columns_) {
        $columns = array();

        foreach (array_keys($columns_) as $key) {
            $columns[$key] = $columns_[$key];

            if ($key == 'name')
                $columns['term-image'] = __('Image', 'wpbdp-customizations');
        }

        return $columns;
    }

    public function _admin_custom_category_column($out, $column_name, $term_id) {
        if ($column_name != 'term-image')
            return $out;

        $term = get_term($term_id, WPBDP_CATEGORY_TAX);

        $html  = '';
        if ($term_image = $this->get_term_image($term_id)) {
            $html .= sprintf('<img src="%s" class="wpbdp-category-image-admin-thumbnail" />', $term_image['url']);
        } else {
            $html .= '-';
        }

        $html .= '<div class="row-actions">';
        $html .= '<span class="edit">';
        $html .= edit_term_link($term_image ? __('Change Image', 'wpbdp-customizations') : __('Add Image', 'wpbdp-categories'), '', '', $term, false);
        $html .= '</span>';
        $html .= '</div>';

        return $html;
    }


    public function _upload_image_scripts() {
        $scripts = <<<EOT
            function wpbdp_category_images_done(upload) {
                jQuery('#TB_closeWindowButton').click();
                jQuery('#category-image-input-path').val(upload.file);
                jQuery('#category-image-input-url').val(upload.url);
                jQuery('#category-image-preview .image-preview').html('<img src="' + upload.url + '" />').show();
                jQuery('#category-image-preview a.delete-image').show();
            }

            function wpbdp_category_images_delete() {
                jQuery('#category-image-input-path').val('');
                jQuery('#category-image-input-url').val('');
                jQuery('#category-image-preview .image-preview').html('');
                jQuery('#category-image-preview a.delete-image').hide();

                return false;
            }
EOT;

        echo '<style type="text/css">';
        echo '#category-image-preview img { max-width: 120px; max-height: 120px; border: solid 1px #444; }';
        echo '#category-image-preview { margin-bottom: 10px; }';
        echo '#category-image-preview a.delete-image { display: block; color: red; }';
        echo 'img.wpbdp-category-image-admin-thumbnail { max-width: 50px; }';
        echo '</style>';

        echo '<script type="text/javascript">';
        echo $scripts;
        echo '</script>';
    }

    public function _upload_image() {
        echo '<script type="text/javascript">';
        echo 'parent.jQuery("#TB_window, #TB_iframeContent").width(350).height(150)';
        echo '</script>';

        if (isset($_FILES['image_upload']) && $_FILES['image_upload']['error'] == 0) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            if ($upload = wp_handle_upload($_FILES['image_upload'], array('test_form' => FALSE))) {
                if (!isset($upload['error'])) {
                    $width = intval(wpbdp_get_option('categories-images-width'));
                    $height = intval(wpbdp_get_option('categories-images-height'));

                    // keep images for this term id registered so we dont leave uploaded images that aren't in use
                    $this->register_temp_image( $_GET['term_id'], $upload['file'] );

                    // TODO: resize images (image_resize())

                    echo '<script type="text/javascript">';
                    echo sprintf('parent.wpbdp_category_images_done(%s);', json_encode($upload));
                    echo '</script>';
                } else {
                    print $upload['error'];
                }
            }
        }

        echo '<div class="wrap">';
        echo '<form action="" method="POST" enctype="multipart/form-data">';
        echo '<strong>' . __('Upload Image', 'wpbdp-customizations') . '</strong><br />';
        echo '<input type="file" name="image_upload" />';
        echo sprintf('<input type="submit" value="%s" class="button" />', __('Upload', 'wpbdp-categories'));
        echo '</form>';
        echo '</div>';
        exit;
    }

    public function _category_edit_form_fields($term) {
        echo '<tr class="form-field">';
        echo '<th scope="row" valign="top">';
        echo '<label for="category-image">';
        echo __('Category Image', 'wpbdp-customizations');
        echo '</label>';
        echo '</th>';
        echo '<td>';

        echo '<div id="category-image-preview">';
        echo '<div class="image-preview">';
        if ($category_image = $this->get_term_image($term->term_id)) {
            echo sprintf('<img src="%s" />', $category_image['url']);
        }
        echo '</div>';
        echo sprintf('<a href="#" onclick="wpbdp_category_images_delete();" class="delete-image" style="display: %s;">%s</a>', $category_image ? 'block' : 'none', __('Delete', 'wpbdp-categories'));
        echo '</div>';

        echo sprintf('<input id="category-image-input-path" type="hidden" name="category_image[path]" value="%s" />', $category_image ? $category_image['path'] : '');
        echo sprintf('<input id="category-image-input-url" type="hidden" name="category_image[url]" value="%s" />', $category_image ? $category_image['url']: '');

        echo sprintf('<a href="%s" class="thickbox button-primary">%s</a>',
                     add_query_arg(array('action' => 'wpbdp-category-images-upload',
                                         'term_id' => $term->term_id,
                                         'TB_iframe' => 1),
                                   admin_url('admin-ajax.php')),
                     __('Upload Image', 'wpbdp-categories')  );
        echo '</td>';
        echo '</tr>';
    }

    public function _category_update($term_id, $tt_id, $taxonomy) {
        if (isset($_POST['category_image'])) {
            $_POST = stripslashes_deep( $_POST );

            $path = wpbdp_getv($_POST['category_image'], 'path', null);
            $url = wpbdp_getv($_POST['category_image'], 'url', null);

            if (!empty($path) && !empty($url)) {
                $this->set_term_image( $term_id, $path, true );
            } else {
                $this->set_term_image( $term_id, null, true );
            }
        }
    }

    public function _categories_list_css( $css ) {
        if ( !wpbdp_get_option('categories-use-images') )
            return $css;

        return $css . ' with-images ';
    }

    public function _categories_list_item_css( $css, $term ) {
        if ( !wpbdp_get_option('categories-use-images') )
            return $css;

        $image = $this->get_term_image($term->term_id);

        if ( $image ) {
            return $css . ' with-image ';
        }

        return $css . ' no-image ';
    }

    public function _categories_list_item($item_html, $term) {
        if ( !wpbdp_get_option('categories-use-images') )
            return $item_html;

        $image = $this->get_term_image($term->term_id);

        $image_html = '';

        if ($image) {
            $image_html .= sprintf('<a href="%s"><img src="%s" class="category-image" style="width: %dpx !important; height: %dpx !important; max-width: %dpx; max-height: %dpx;" /></a>',
                             get_term_link( $term, WPBDP_CATEGORY_TAX ),
                             $image['url'],
                             wpbdp_get_option('categories-images-width'),
                             wpbdp_get_option('categories-images-height'),
                             wpbdp_get_option('categories-images-width'),
                             wpbdp_get_option('categories-images-height')
                            );
        } else {
            $image_html .= sprintf('<a href="%s"><div class="category-image-placeholder" style="width: %dpx; height: %dpx;"></div></a>',
                             get_term_link( $term, WPBDP_CATEGORY_TAX ),
                             wpbdp_get_option('categories-images-width'),
                             wpbdp_get_option('categories-images-height')
                            );
        }

        $item_html = $image_html . $item_html;
        return $item_html;
    }

    public function _enqueue_scripts() {
        wp_enqueue_style( 'wpbdp-category-icons-module', plugins_url( '/resources/styles.min.css', __FILE__ ), array('wpbdp-base-css') );
    }    

}

WPBDP_CategoryIconsModule::instance();
