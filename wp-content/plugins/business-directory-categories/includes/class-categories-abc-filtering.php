<?php

/**
 * This class provides all the functionality for the ABC filtering bar.
 * @since 3.5.1
 */
class WPBDP_Categories_ABC_Filtering {

    const VALID_CHARS = '_0abcdefghijklmnopqrstuvwxyz';
    const LETTERS = 'abcdefghijklmnopqrstuvwxyz';


    function __construct() {
        add_filter( 'wpbdp_template_vars', array( &$this, 'template_vars' ), 10, 2 );
        add_filter( 'wpbdp_query_clauses', array( &$this, 'query_letter_filter' ) );
        add_action( 'wpbdp_enqueue_scripts', array( &$this, 'enqueue_styles' ) );
    }

    function template_vars( $vars, $template_name ) {
        if ( ! in_array( $template_name, array( 'businessdirectory-listings' ), true ) )
            return $vars;

        global $wpbdp;
        $action = $wpbdp->controller->action;

        if ( 'main' == $action )
            return $vars;

        $vars['__page__']['before_content'] .= $this->abc_filter_html();
        return $vars;
    }

    function query_letter_filter( $clauses ) {
        global $wpdb;

        $current_letter = $this->get_current_letter();

        if ( false === $current_letter )
            return $clauses;

        switch ( $current_letter ) {
            case '_':
                $clauses['where'] .= " AND ( LOWER(LEFT({$wpdb->posts}.post_title, 1))  ) NOT REGEXP '[a-zA-Z0-9]+'";
                break;

            case '0':
                $clauses['where'] .= " AND ( LOWER(LEFT({$wpdb->posts}.post_title, 1))  ) REGEXP '[0-9]+'";
                break;

            default:
                $clauses['where'] .= $wpdb->prepare( " AND ( LOWER(LEFT({$wpdb->posts}.post_title, 1)) = %s )",
                                                     $current_letter );

                break;
        }

        return $clauses;
    }

    function enqueue_styles() {
        wp_enqueue_style( 'wpbdp-abc-filtering', plugins_url( '/resources/abc.css', dirname( __FILE__ ) ) );
    }

    private function get_current_letter() {
        $l =  array_key_exists( 'l', $_GET ) ? trim( strtolower( $_GET['l'] ) ) : false;

        if ( false === $l )
            return false;

        if ( ! in_array( $l, str_split( self::VALID_CHARS ), true ) )
            return false;

        return $l;
    }

    private function abc_filter_html() {
        $letters = array();
        $letters['_'] = array( '#', 1 );
        $letters['0'] = array( '0-9', 1 );

        foreach ( str_split( self::LETTERS ) as $l ) {
            $letters[ $l ] = array( strtoupper( $l ), 1 );
        }


        $html  = '';
        $html .= '<div class="wpbdp-abc-filtering wpbdp-hide-on-mobile">';

        foreach ( $letters as $l => $info ) {
            $html .= sprintf( '<span class="letter %s">', ( $l === $this->get_current_letter() ? 'current' : '' ) );

            if ( $info[1] > 0 )
                $html .= sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( 'l', $l ) ), $info[0] );
            else
                $html .= $info[0];

            $html .= '</span>';
        }

        if ( $this->get_current_letter() ) {
            $html .= sprintf( '<a href="%s" class="reset">%s</a>',
                              remove_query_arg( 'l' ),
                              __( '(Reset)', 'wpbdp-categories' ) );
        }

        $html .= '</div>';

        return $html;
    }

}
