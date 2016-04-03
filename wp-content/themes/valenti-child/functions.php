<?php /* To overwrite a function from either functions.php or from library/core.php, overwrite it in this file */ 

/*********************
CHILD STYLESHEET ENQUEUEING
*********************/
if ( ! function_exists( 'cb_script_loaders_child' ) ) {   
    function cb_script_loaders_child() {

        add_action('wp_enqueue_scripts', 'cb_scripts_and_styles_child', 999);
    }
}

add_action('after_setup_theme','cb_script_loaders_child', 16);
    

if ( ! function_exists( 'cb_scripts_and_styles_child' ) ) {
       
    function cb_scripts_and_styles_child() {
                
      if (!is_admin()) {
        // Register child stylesheet for RTL/LTR
        if ( is_rtl() ) {
            wp_register_style( 'cb-child-stylesheet',  get_stylesheet_directory_uri() . '/style-rtl.css', array(), '1.0', 'all' );
        } else {
            wp_register_style( 'cb-child-stylesheet',  get_stylesheet_directory_uri() . '/style.css', array(), '1.0', 'all' );
        }
        wp_enqueue_style('cb-child-stylesheet'); // enqueue it
      }
    }
    
}
?>