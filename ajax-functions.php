<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Esci se viene acceduto direttamente
}

// ** Funzione per gestire la richiesta AJAX di Login **
add_action( 'wp_ajax_salone_login_action', 'salone_login_ajax_handler' ); // Per utenti loggati
add_action( 'wp_ajax_nopriv_salone_login_action', 'salone_login_ajax_handler' ); // Per utenti non loggati
function salone_login_ajax_handler() {
    // Verifica del Nonce per sicurezza
    check_ajax_referer( 'salone_login_nonce', 'nonce' );

    $login_identifier = sanitize_text_field( $_POST['login_identifier'] ); // NUOVO CAMPO - Sanifica input generico
    $password       = $_POST['password']; // NON sanitizzare la password qui!

    $creds = array();

    // ** Determina se login_identifier è email, username o cellulare e imposta user_login di conseguenza **
    if ( is_email( $login_identifier ) ) {
        // Login tramite Email
        $creds['user_login'] = $login_identifier;
    } else {
        // Potrebbe essere Username o Cellulare. Proviamo a cercare per cellulare nei meta dati utente
        $user_by_cellulare = get_users( array(
            'meta_key'    => 'salone_cellulare',
            'meta_value'  => $login_identifier,
            'number'      => 1, // Cerchiamo solo un utente
            'fields'      => 'login' // Restituisci solo il login (username)
        ) );

        if ( ! empty( $user_by_cellulare ) ) {
            // Login tramite Cellulare (utente trovato con quel numero)
            $creds['user_login'] = $user_by_cellulare[0]; // Prendiamo il primo (e unico) username trovato
        } else {
            // Login tramite Username (se non è email e non trovato per cellulare, assumiamo sia username)
            $creds['user_login'] = $login_identifier; // Altrimenti prova a fare login con quello che è stato inserito come username
        }
    }


    $creds['user_password'] = $password;
    $creds['remember']      = true; // o false, a seconda delle tue preferenze

    $user = wp_signon( $creds, false ); // Effettua il login con wp_signon

    if ( is_wp_error( $user ) ) {
        // Errore di login
        wp_send_json_error( array( 'message' => $user->get_error_message() ) );
    } else {
        // Login avvenuto con successo
        wp_send_json_success( array( 'message' => 'Login effettuato con successo!', 'redirect_url' => home_url() ) ); // Puoi reindirizzare dove vuoi
    }

    wp_die(); // Importante: Termina l'esecuzione di WordPress dopo la risposta AJAX
}


// ** Funzione per gestire la richiesta AJAX di Registrazione **
add_action( 'wp_ajax_salone_registrazione_action', 'salone_registrazione_ajax_handler' ); // Per utenti loggati e non loggati (in genere solo non loggati si registrano)
add_action( 'wp_ajax_nopriv_salone_registrazione_action', 'salone_registrazione_ajax_handler' ); // Per utenti non loggati
function salone_registrazione_ajax_handler() {
    // Verifica del Nonce per sicurezza
    check_ajax_referer( 'salone_login_nonce', 'nonce' ); // Usa lo stesso nonce per semplicità, ma potresti crearne uno specifico

    $nome      = sanitize_text_field( $_POST['nome'] );        // NUOVO CAMPO - Sanifica testo
    $cognome   = sanitize_text_field( $_POST['cognome'] );     // NUOVO CAMPO - Sanifica testo
    $email     = sanitize_email( $_POST['email'] );           // CAMPO ESISTENTE - Sanifica email
    $cellulare = sanitize_text_field( $_POST['cellulare'] );   // NUOVO CAMPO - Sanifica testo (potrebbe servire validazione più specifica)
    $username  = sanitize_user( $_POST['username'], true );  // CAMPO ESISTENTE (ora facoltativo) - Sanifica username, allow_unicode true per nomi unicode
    $password  = $_POST['password'];                          // CAMPO ESISTENTE - NON sanitizzare password!
    $password2 = $_POST['password2'];                         // CAMPO ESISTENTE - NON sanitizzare password!

    // **Validazione Lato Server (più robusta)**
    if ( empty( $nome ) ) {
        wp_send_json_error( array( 'message' => 'Il Nome è obbligatorio.' ) );
    }
    if ( empty( $cognome ) ) {
        wp_send_json_error( array( 'message' => 'Il Cognome è obbligatorio.' ) );
    }
    if ( empty( $email ) || ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'Email non valida o mancante.' ) );
    }
    if ( empty( $cellulare ) ) {
        wp_send_json_error( array( 'message' => 'Il Cellulare è obbligatorio.' ) );
    }
    if ( strlen( $password ) < 6 ) {
        wp_send_json_error( array( 'message' => 'La password deve essere di almeno 6 caratteri.' ) );
    }
    if ( $password !== $password2 ) {
        wp_send_json_error( array( 'message' => 'Le password non corrispondono.' ) );
    }
    if ( ! empty( $username ) && username_exists( $username ) ) { // Controlla username solo se fornito
        wp_send_json_error( array( 'message' => 'Username già esistente.' ) );
    }
    if ( email_exists( $email ) ) {
        wp_send_json_error( array( 'message' => 'Email già registrata.' ) );
    }

    // **Genera Username Automaticamente se non fornito**
    if ( empty( $username ) ) {
        $base_username = sanitize_user( strtolower( $nome . '-' . $cognome ), true ); // Crea username base da nome e cognome, lowercase e sanificato
        $username = $base_username;
        $i = 1;
        while ( username_exists( $username ) ) { // Se username esiste già, aggiungi un numero progressivo
            $username = $base_username . '-' . $i;
            $i++;
        }
    }

    // **Crea l'utente**
    $user_id = wp_create_user( $username, $password, $email );

    if ( is_wp_error( $user_id ) ) {
        // Errore di registrazione
        wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
    } else {
        // **Salva i Metadati Utente (Nome, Cognome, Cellulare)**
        update_user_meta( $user_id, 'salone_nome', $nome );
        update_user_meta( $user_id, 'salone_cognome', $cognome );
        update_user_meta( $user_id, 'salone_cellulare', $cellulare );

        // Registrazione avvenuta con successo
        wp_send_json_success( array( 'message' => 'Registrazione effettuata con successo! Sarai reindirizzato alla pagina di login...', 'redirect_url' => wp_login_url() ) );
    }

    wp_die(); // Importante: Termina l'esecuzione di WordPress dopo la risposta AJAX
}

// ** Funzione per gestire la richiesta AJAX di Recupero Password **
add_action( 'wp_ajax_salone_password_reset_request_action', 'salone_password_reset_request_ajax_handler' ); // Per utenti loggati
add_action( 'wp_ajax_nopriv_salone_password_reset_request_action', 'salone_password_reset_request_ajax_handler' ); // Per utenti non loggati
function salone_password_reset_request_ajax_handler() {
    // Verifica del Nonce per sicurezza
    check_ajax_referer( 'salone_login_nonce', 'nonce' ); // Usa lo stesso nonce

    $user_login = sanitize_text_field( $_POST['user_login'] ); // Sanifica input email o username

    if ( empty( $user_login ) ) {
        wp_send_json_error( array( 'message' => 'Inserisci Email o Username.' ) );
    }

    if ( is_email( $user_login ) ) {
        $user_data = get_user_by( 'email', trim( $user_login ) );
        if ( ! $user_data ) {
            wp_send_json_error( array( 'message' => 'Nessun utente trovato con questa email.' ) );
        }
        $user_login_type = 'email';
    } else {
        $user_data = get_user_by( 'login', trim( $user_login ) );
        if ( ! $user_data ) {
            // Se non trovato per username, prova a cercare per cellulare
            $users_by_cellulare = get_users( array(
                'meta_key'    => 'salone_cellulare',
                'meta_value'  => $user_login,
                'number'      => 1 // Cerchiamo solo un utente
            ) );
            if ( ! empty( $users_by_cellulare ) ) {
                $user_data = $users_by_cellulare[0]; // Prendi il primo utente trovato
                $user_login_type = 'cellulare'; // Traccia che l'utente è stato trovato tramite cellulare
            } else {
                wp_send_json_error( array( 'message' => 'Nessun utente trovato con questo username o cellulare.' ) );
            }
        } else {
            $user_login_type = 'username';
        }
    }

    $user_id    = $user_data->ID;
    $user_email = $user_data->user_email;
    $user_login_name = $user_data->user_login; // Username

    // **Genera Password Reset Key**
    $reset_key = wp_generate_password( 20, false ); // Chiave casuale di 20 caratteri
    do_action( 'retrieve_password', $user_login_name ); // Azione WordPress per password recovery

    // **Salva Reset Key come User Meta (con scadenza - opzionale, per ora non implementata)**
    update_user_meta( $user_id, 'salone_password_reset_key', $reset_key );
    update_user_meta( $user_id, 'salone_password_reset_expiry', time() + DAY_IN_SECONDS ); // Scadenza dopo 1 giorno (opzionale per ora)

    // **Costruisci Password Reset Link**
    $reset_url = add_query_arg( array(
        'action'    => 'salone_reset_password', // Azione per la pagina di reset password
        'key'       => $reset_key,
        'login'     => $user_login_name
    ), home_url( 'reset-password' ) ); // Pagina "reset-password" (dovrai crearla)

    // **Invia Email con Password Reset Link**
    $subject = 'Recupero Password - ' . get_bloginfo('name');
    $message = '<p>Ciao ' . $user_login_name . ',</p>';
    $message .= '<p>Hai richiesto di resettare la password per il tuo account su ' . get_bloginfo('name') . '.</p>';
    $message .= '<p>Clicca sul seguente link per impostare una nuova password:</p>';
    $message .= '<p><a href="' . esc_url( $reset_url ) . '">' . esc_url( $reset_url ) . '</a></p>';
    $message .= '<p>Se non hai richiesto il reset della password, ignora questa email. La tua password attuale rimarrà invariata.</p>';
    $headers = array('Content-Type: text/html; charset=UTF-8');

    $email_sent = wp_mail( $user_email, $subject, $message, $headers );

    if ( $email_sent ) {
        wp_send_json_success( array( 'message' => 'Email di recupero password inviata con successo. Controlla la tua casella di posta (e la cartella spam, se non la trovi).<br><br> <a href="#" id="salone-back-to-login-from-reset">Torna al Login</a>' ) ); // Link "Torna al Login" nel messaggio di successo
    } else {
        wp_send_json_error( array( 'message' => 'Errore durante l\'invio dell\'email di recupero password. Riprova più tardi.' ) );
    }

    wp_die(); // Importante: Termina l'esecuzione di WordPress
}