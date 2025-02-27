<?php
/**
 * Plugin Name: Salone Login e Registrazione
 * Plugin URI:  https://nomesito.com/salone-login-registrazione
 * Description: Plugin personalizzato per la gestione di login e registrazione AJAX per il sito web del salone da parrucchiera.
 * Version:     1.0.0
 * Author:      [Il tuo nome]
 * Author URI:  https://nomesito.com
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: salone-login-registrazione
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Esci se viene acceduto direttamente
}

// Definisci la costante del percorso del plugin
define( 'SALONE_LOGIN_PATH', plugin_dir_path( __FILE__ ) );

// Include file con le funzioni AJAX
require_once SALONE_LOGIN_PATH . 'includes/ajax-functions.php';

// Azioni per l'enqueue degli script e degli stili
add_action( 'wp_enqueue_scripts', 'salone_enqueue_plugin_scripts_styles' );
function salone_enqueue_plugin_scripts_styles() {
    // Registra e carica il file CSS
    wp_register_style( 'salone-login-style', plugin_dir_url( __FILE__ ) . 'assets/css/stile-salone-login.css' );
    wp_enqueue_style( 'salone-login-style' );

    // Registra e carica il file Javascript
    wp_register_script( 'salone-login-script', plugin_dir_url( __FILE__ ) . 'assets/js/script-salone-login.js', array('jquery'), '1.0.0', true );
    wp_enqueue_script( 'salone-login-script' );

    // Passa variabili Javascript dal PHP al JS (es. ajax_url, nonce)
    wp_localize_script( 'salone-login-script', 'salone_ajax_params', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'salone_login_nonce' ), // Nonce specifico per il tuo plugin
    ));
}

// Funzioni per mostrare i pulsanti trigger dei modal (MODIFICATO per modal)
function salone_mostra_pulsante_login() {
    return '<button id="salone-login-button" class="salone-pulsante-modal">Login</button>';
}

function salone_mostra_pulsante_registrazione() {
    return '<button id="salone-registrazione-button" class="salone-pulsante-modal">Registrati</button>';
}

// [Shortcode di esempio per mostrare il pulsante di login (MODIFICATO per modal)]
add_shortcode( 'salone_login_form', 'salone_mostra_pulsante_login' ); // Manteniamo lo stesso shortcode name per compatibilità

// [Shortcode di esempio per mostrare il pulsante di registrazione (MODIFICATO per modal)]
add_shortcode( 'salone_registrazione_form', 'salone_mostra_pulsante_registrazione' ); // Manteniamo lo stesso shortcode name per compatibilità

?>