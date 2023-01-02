<?php
/**
 * Plugin Name:       JWT Publish
 * Plugin URI:        https://github.com/ricardkelly/wordpress_JWT_Publish
 * Description:       Provides signed JWT for the logged in user on all pages to enable AJAX components to authenticate to external APIs as the user.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Ricard Kelly
 * Author URI:        https://ricardkelly.com/
 */

class RK_JWT_Plugin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'create_plugin_settings_page' ) );
        add_action( 'admin_init', array( $this, 'setup_sections' ) );
        add_action( 'admin_init', array( $this, 'setup_fields' ) );
        add_action( 'wp_footer', array( $this, 'create_jwt_block' ) );;
    }
 
    public function create_plugin_settings_page() {
        $page_title = 'JWT Publish Settings';
        $menu_title = 'JWT Publish';
        $capability = 'manage_options';
        $slug = 'rk_jwt';
        $callback = array( $this, 'plugin_settings_page_content' );
        add_submenu_page( 'options-general.php', $page_title, $menu_title, $capability, $slug, $callback );
    }

    public function plugin_settings_page_content() {
        echo '<div class="wrap"><h2>JWT Publish Settings</h2>';
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ){
            $this->admin_notice();
        }
        echo '<form method="POST" action="options.php"';
        settings_fields( 'rk_jwt' );
        do_settings_sections( 'rk_jwt' );
        submit_button();
        echo '</form></div>';
    }
     
    public function admin_notice() {
        echo '<div class="notice notice-success is-dismissible"><p>Your settings have been updated!</p></div>';
    }
 
    public function setup_sections() {
        add_settings_section( 'rk_jwt_section', 'Signing Key', array( $this, 'section_callback' ), 'rk_jwt' );
    }
 
    public function section_callback( $arguments ) {
        echo 'The private key for signing JWT tokens is provided here.';
    }

    public function setup_fields() {
        $fields = array(
            array(
                'uid' => 'rk_jwt_signingkey',
                'label' => 'Private key',
                'section' => 'rk_jwt_section',
                'type' => 'textarea',
                'default' => '',
                'placeholder' => 'paste PEM key here',
                'helper' => '',
                'supplemental' => ''
            )
        );
        foreach( $fields as $field ){
        add_settings_field( $field['uid'], $field['label'], array( $this, 'field_callback' ), 'rk_jwt', $field['section'], $field );
        register_setting( 'rk_jwt', $field['uid'] );
        }
    }
 
    public function field_callback( $arguments ) {
        $value = get_option( $arguments['uid'] );
        if( ! $value ) {
            $value = $arguments['default'];
        }
        switch( $arguments['type'] ){
            case 'textarea':
                printf( '<textarea name="%1$s" id="%1$s" placeholder="%2$s" rows="5" cols="50">%3$s</textarea>', $arguments['uid'], $arguments['placeholder'], $value );
                break;
        }
        if( $helper = $arguments['helper'] ){
            printf( '<span class="helper"> %s</span>', $helper );
        }
        if( $supplemental = $arguments['supplemental'] ){
            printf( '<p class="description">%s</p>', $supplemental );
        }
    }

    public function create_jwt_block() {
        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            $subject = $current_user->user_login;
            $header = json_encode([ 'typ' => 'JWT', 'alg'  => 'RS256', ]);
            $payload = json_encode([
                'iss'   => get_site_url(),
                'sub'   => $subject,
                'iat'   => time(),
                'exp'   => time() + (60*60*24),
            ]);
            $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));    
            $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
            $data = $base64UrlHeader . "." . $base64UrlPayload;
            $privateKey = $setting = get_option('rk_jwt_signingkey');
            openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            $jwt = $data . "." . $base64UrlSignature;
            echo "<script>\nfunction get_jwt() { return \"" . $jwt . "\"; }</script>\n";
        }
    }
}
new RK_JWT_Plugin();
?>
