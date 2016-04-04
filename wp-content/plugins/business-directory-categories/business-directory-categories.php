<?php
/*
 Plugin Name: Business Directory Plugin - Enhanced Categories Module
 Plugin URI: http://www.businessdirectoryplugin.com
 Version: 3.6.2
 Author: D. Rodenbaugh
 Description: Category goodies for Business Directory Plugin, including parent/child hierarchy navigation, images on categories and more.
 Author URI: http://businessdirectoryplugin.com
*/

require_once( plugin_dir_path( __FILE__ ) . 'category-icons.php' );

class WPBDP_CategoriesModule {

    const VERSION = '3.6.2';
    const REQUIRED_BD_VERSION = '3.5.6';

    private static $instance = null;

    private $mode = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'load_i18n' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_action( 'wpbdp_modules_loaded', array( $this, '_init' ) );

        $this->category_icons = WPBDP_CategoryIconsModule::instance();
    }

    public function admin_notices() {
        if ( ! current_user_can( 'administrator' ) )
            return;

        if ( function_exists( 'wpbdp_get_version' ) && version_compare( wpbdp_get_version(), self::REQUIRED_BD_VERSION, '>=' ) ) {
        } else {
            echo sprintf( __( '<div class="error"><p>Business Directory - Enhanced Categories Module requires Business Directory Plugin >= %s.</p></div>', 'wpbdp-categories' ) , self::REQUIRED_BD_VERSION );
        }
    }    

    public function load_i18n() {
        load_plugin_textdomain( 'wpbdp-categories', false, trailingslashit( basename( dirname( __FILE__ ) ) ) . 'translations/' );        
    }    

    public function _init() {
        if ( version_compare( WPBDP_VERSION, self::REQUIRED_BD_VERSION, '<' ) )
            return;

        add_action( 'wpbdp_register_settings', array( $this, '_register_settings' ), 10, 1 );

        add_action( 'wpbdp_before_category_page', array( $this, '_category_cats' ), 10, 1 );
        add_filter( 'wpbdp_category_page_listings', array( $this, '_hide_listings' ), 10, 2 );
        add_action( 'pre_get_posts', array( &$this, '_remove_subcategories_from_query' ), 20 );
        add_filter( 'wpbdp_main_categories', array( $this, '_main_categories' ) );

        add_filter( 'wpbdp_render_field_inner', array( $this, '_category_field' ), 10, 4 );
        add_action( 'wp_enqueue_scripts', array( &$this, '_enqueue_scripts' ) );
        add_action( 'wp_ajax_wpbdp-categories', array( $this, '_ajax' ) );
        add_action( 'wp_ajax_nopriv_wpbdp-categories', array( $this, '_ajax' ) );

        add_action( 'wp_print_styles', array( $this, '_custom_css' ) );

        add_action( 'wpbdp_modules_init', array( &$this, '_init_abc' ) );
    }

    function _init_abc() {
        if ( ! wpbdp_get_option( 'abc-filtering' ) )
            return;

        require_once( plugin_dir_path( __FILE__ ) . 'includes/class-categories-abc-filtering.php' );
        $this->abc_filtering = new WPBDP_Categories_ABC_Filtering();
    }

    public function _register_settings( &$settingsapi ) {
        $settingsapi->add_setting( 'listings:post/sorting',
                                   'abc-filtering',
                                   _x( 'Enable ABC filtering?', 'settings', 'wpbdp-categories' ),
                                   'boolean',
                                   false,
                                   _x( 'Displays links on top of listings for alphabetic filtering.', 'settings', 'wpbdp-categories' ) );

        $s = $settingsapi->add_section( 'listings',
                                        'listings/categorymode',
                                        _x( 'Main Directory Behavior', 'settings', 'wpbdp-categories' ),
                                        _x( 'Settings related to the Improved Categories module.', 'settings', 'wpbdp-categories' ) );
        $settingsapi->add_setting( $s,
                                   'categories-mode',
                                   _x( 'Operation Mode', 'settings', 'wpbdp-categories' ),
                                   'choice',
                                   'parent+child',
                                   '',
                                   array( 'choices' => array(
                                                              array( 'parent+child', _x( 'Parent + Child categories', 'settings', 'wpbdp-categories' ) ),
                                                              array( 'parent', _x( 'Parent only categories', 'settings', 'wpbdp-categories' ) )
                                                            ) ) );
        $settingsapi->add_setting( $s,
                                   'categories-submit-only-in-leafs',
                                   _x( 'Force selection of parent category before child category', 'settings', 'wpbdp-categories' ),
                                   'boolean',
                                   false,
                                   _x( 'Creates separate drop downs on Submit Listing', 'settings', 'wpbdp-categories' ) );

        $settingsapi->add_setting( $s,
                                   'categories-columns',
                                   _x( 'Number of category columns to use', 'settings', 'wpbdp-categories' ),
                                   'choice',
                                   '2',
                                   __( 'BD will try to honor this setting as much as possible, but custom CSS or theme code could prevent this from working.' , 'wpbdp-categories'),
                                   array( 'choices' => array( '1', '2', '3', '4', '5' ) ) );
    }

    public function _category_cats( $category ) {
        if ( ! $category )
            return;

        // if ( $this->get_mode() != 'parent' )
        //     return;

        if ( !$this->is_leaf_category( $category ) ) {
            if ( $cats = wpbdp_list_categories( array( 'parent' => $category, 'parent_only' => true, 'hide_empty' => wpbdp_get_option( 'hide-empty-categories' ), 'no_items_msg' => '' ) ) ) {
                echo $cats;
                echo str_repeat( '<br />', 2 );    
            }
            
        }
    }

    public function _hide_listings( $listings, $category )  {
        if ( ! $category )
            return $listings;

        if ( !$this->is_leaf_category( $category ) && intval( $category->count ) == 0 )
            return '';

        return $listings;
    }

    public function _remove_subcategories_from_query( $query ) {
        if ( empty( $query->query_vars['wpbdp_action'] ) || 'browsecategory' != $query->query_vars['wpbdp_action'] )
            return;

        $tax_query = $query->get( 'tax_query' );

        foreach ( $tax_query as &$t ) {
            if ( WPBDP_CATEGORY_TAX == $t['taxonomy'] )
                $t['include_children'] = false;
        }

        $query->set( 'tax_query', $tax_query );
    }

    public function _main_categories( $html ) {
        if ( $this->get_mode() != 'parent' )
            return $html;

        return wpbdp_list_categories( array( 'hide_empty' => wpbdp_get_option( 'hide-empty-categories' ), 'parent_only' => true ) );
    }

    public function _category_field( $field_inner, &$field, $value, $render_context ) {
        if ( $field->get_association() != 'category' || !wpbdp_get_option( 'categories-submit-only-in-leafs' ) || 'search' == $render_context )
            return $field_inner;

        if ( is_array( $value ) )
            $value = intval( $value[0] );

        $html  = '';
        $html .= '<input type="hidden" class="wpbdp-x-category-selector-value" name="listingfields[' . $field->get_id() . '][]" value="' . $value  . '" />';

        if ( $value && ( $term = get_term( $value, WPBDP_CATEGORY_TAX ) ) ) {
            $terms = array();

            $current = $term;
            while ( $current ) {
                $terms[] = $current;
                $current = $current->parent ? get_term( $current->parent, WPBDP_CATEGORY_TAX ) : null;
            }

            $terms = array_reverse( $terms );
            foreach ( $terms as $n => &$t ) {
                $fieldset[] = $this->render_selector( $n, $t->term_id, $t->parent );
            }

            $html .= implode('', $fieldset );
        } else {
            $html .= $this->render_selector( 0, 0 );           
        }

        $html .= sprintf( '<img src="%s" class="wpbdp-x-category-loading" style="border: none; box-shadow: none; display: none;" />', plugins_url( '/resources/loading.gif', __FILE__ ) );

        return $html;
    }

    private function render_selector( $depth=0, $selected=0, $parent=null ) {
        $ajaxurl = add_query_arg( 'action', 'wpbdp-categories', wpbdp_ajaxurl() );

        $html  = '';
        $html .= wp_dropdown_categories( array(
                'show_option_none' => __( '-- Select a category --', 'wpbdp-categories' ),
                'taxonomy' => WPBDP_CATEGORY_TAX,
                'selected' => $selected,
                'orderby' => wpbdp_get_option( 'categories-order-by' ),
                'order' => wpbdp_get_option( 'categories-sort' ),
                'hide_empty' => false,
                'hierarchical' => true,
                'depth' => 1,
                'echo' => false,
                'id' => '',
                'name' => '',
                'class' => 'wpbdp-x-category-selector',
                'child_of' => $parent ? ( is_object( $parent ) ? $parent->term_id : intval( $parent ) ) : 0
            ) );

        $html = preg_replace( "/\\<select(.*)name=('|\")(.*)('|\")(.*)\\>/uiUs",
                              "<select data-depth=\"{$depth}\" data-url=\"{$ajaxurl}\" $1 $5 style=\"display: block;\">",
                              $html );

        return $html;
    }

    public function _enqueue_scripts() {
        wp_enqueue_script( 'wpbdp-categories',
                           plugins_url( 'resources/categories-module.min.js', __FILE__ ),
                           array( 'jquery' ),
                           self::VERSION,
                           true
                         );
    }

    public function _ajax() {
        $category = wpbdp_getv( $_REQUEST, 'category', 0 );

        $response = array( 'ok' => true, 'leaf' => false, 'html' => '' );

        if ( $this->is_leaf_category( $category ) ) {
            $response['leaf'] = true;
        } else {
            $response['html'] = $this->render_selector( 0, 0, $category );
        }

        header( 'Content-Type: application/json' );
        echo json_encode( $response );
        exit;
    }

    public function _custom_css() {
        $columns = max( 1, min( intval( wpbdp_get_option( 'categories-columns', 2 ) ), 5 ) );
        $width = round( 100.0 / intval( $columns ), 0 );

        echo '<style type="text/css">';
        echo 'ul.wpbdp-categories > li {';
        echo sprintf( 'width: %d%% !important;', $width );
        echo '}';
        echo '</style>';
    }

    /* API */
    public function get_mode() {
        if ( !isset( $this->mode ) )
            $this->mode = wpbdp_get_option( 'categories-mode', 'parent+child' );
        
        return $this->mode;
    }

    public function is_leaf_category( $category ) {
        return count( get_term_children( is_object( $category ) ? $category->term_id : intval( $category ), WPBDP_CATEGORY_TAX ) ) == 0;
    }

}


$_wpbdp_categories_module = WPBDP_CategoriesModule::instance();
