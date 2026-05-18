<?php
/**
 * Plugin Name: PCPal Whitelist Redirect met Magic Key
 * Description: Redirect alle niet-ingelogde bezoekers naar een instelbare URL, behalve gewhiteliste IP's of met een vaste Magische Sleutel.
 * Version: 1.4
 * Author: J.M. van der Pal (PCPal)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. DE REDIRECT LOGICA
 */
add_action( 'template_redirect', 'pwr_redirect_logic' );

function pwr_redirect_logic() {
    // 1. Ingelogde gebruikers mogen alles zien
    if ( is_user_logged_in() ) {
        return;
    }

    // 2. Controleer of de Magische Sleutel in de URL staat (?key=...)
    $saved_key = get_option( 'pwr_magic_key', '' );
    if ( ! empty( $saved_key ) && isset( $_GET['key'] ) && $_GET['key'] === $saved_key ) {
        return; // Sleutel is geldig, stop de redirect!
    }

    // 3. Haal het IP-adres van de bezoeker op
    $visitor_ip = $_SERVER['REMOTE_ADDR'];

    // 4. Haal de whitelist op
    $whitelist_option = get_option( 'pwr_ip_whitelist', '' );
    $whitelisted_ips = array_map( 'trim', explode( "\n", $whitelist_option ) );

    // 5. Controleer of het IP op de lijst staat
    if ( in_array( $visitor_ip, $whitelisted_ips ) ) {
        return; // IP staat op de lijst, stop de redirect!
    }

    // 6. Haal de doel-URL op
    $redirect_url = get_option( 'pwr_redirect_url', 'https://pcpal.nl/?source=jmvdpal.nl' );
    if ( empty( trim( $redirect_url ) ) ) {
        $redirect_url = 'https://pcpal.nl/?source=jmvdpal.nl';
    }

    // 7. Geen toegang? Redirect!
    wp_redirect( $redirect_url );
    exit;
}

/**
 * 2. HET INSTELLINGENMENU MAKEN
 */
add_action( 'admin_menu', 'pwr_add_admin_menu' );
add_action( 'admin_init', 'pwr_settings_init' );

function pwr_add_admin_menu() {
    add_options_page(
        'IP Whitelist & Access Instellingen',
        'IP Whitelist',
        'manage_options',
        'pcpal_whitelist',
        'pwr_options_page'
    );
}

function pwr_settings_init() {
    register_setting( 'pwr_plugin_page', 'pwr_ip_whitelist' );
    register_setting( 'pwr_plugin_page', 'pwr_redirect_url', 'esc_url_raw' );
    register_setting( 'pwr_plugin_page', 'pwr_magic_key', 'sanitize_text_field' );

    add_settings_section(
        'pwr_main_section',
        'Beheer Toegang, Redirect & Magische Sleutels',
        null,
        'pwr_plugin_page'
    );

    add_settings_field(
        'pwr_redirect_url_field',
        'Doel URL voor redirect',
        'pwr_redirect_url_render',
        'pwr_plugin_page',
        'pwr_main_section'
    );

    add_settings_field(
        'pwr_magic_key_field',
        'Magische Sleutel (Bypass)',
                       'pwr_magic_key_render',
                       'pwr_plugin_page',
                       'pwr_main_section'
    );

    add_settings_field(
        'pwr_ip_whitelist_field',
        'Gewhiteliste IP-adressen',
        'pwr_ip_whitelist_render',
        'pwr_plugin_page',
        'pwr_main_section'
    );
}

function pwr_redirect_url_render() {
    $value = get_option( 'pwr_redirect_url', 'https://pcpal.nl/?source=jmvdpal.nl' );
    ?>
    <input type="url" name="pwr_redirect_url" class="regular-text" value="<?php echo esc_attr( $value ); ?>" style="width: 100%; max-width: 400px;">
    <p class="description">Waar moeten niet-geautoriseerde bezoekers naartoe worden gestuurd?</p>
    <?php
}

function pwr_magic_key_render() {
    // Haal strikt de opgeslagen sleutel op, genereer niets automatisch via PHP.
    $value = get_option( 'pwr_magic_key', '' );
    ?>
    <div style="display: flex; gap: 10px; align-items: center;">
    <input type="text" id="pwr_magic_key_input" name="pwr_magic_key" class="regular-text" value="<?php echo esc_attr( $value ); ?>" readonly style="width: 100%; max-width: 400px; font-family: monospace; background: #f0f0f1; border-color: #8c8f94;">
    <button type="button" id="pwr_generate_key_btn" class="button button-secondary">Genereer Nieuwe Sleutel</button>
    <button type="button" id="pwr_clear_key_btn" class="button">Wis Sleutel</button>
    </div>
    <p class="description">
    <?php if ( empty( $value ) ) : ?>
    <strong style="color: #d63638;">Er is momenteel geen magische sleutel actief.</strong> Klik op genereren en sla op om deze functie te gebruiken.
    <?php else : ?>
    Voeg <code>?key=<?php echo esc_attr( $value ); ?></code> toe aan het einde van een URL om de blokkade te omzeilen.
    <?php endif; ?>
    </p>

    <script>
    document.getElementById('pwr_generate_key_btn').addEventListener('click', function(e) {
        e.preventDefault();
        var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var newKey = '';
    for (var i = 0; i < 32; i++) {
        newKey += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('pwr_magic_key_input').value = newKey;
    alert('Nieuwe sleutel gegenereerd! Vergeet niet onderaan de pagina op "Wijzigingen opslaan" te klikken om hem actief te maken.');
    });

    document.getElementById('pwr_clear_key_btn').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('pwr_magic_key_input').value = '';
    alert('Sleutel gewist! Vergeet niet onderaan de pagina op "Wijzigingen opslaan" te klikken om dit door te voeren.');
    });
    </script>
    <?php
}

function pwr_ip_whitelist_render() {
    $value = get_option( 'pwr_ip_whitelist', '' );
    ?>
    <textarea name="pwr_ip_whitelist" rows="8" cols="50" class="large-text" placeholder="123.456.78.90"><?php echo esc_textarea( $value ); ?></textarea>
    <p class="description">Zet elk IP-adres op een nieuwe regel. Jouw huidige IP is: <strong><?php echo $_SERVER['REMOTE_ADDR']; ?></strong></p>
    <?php
}

function pwr_options_page() {
    ?>
    <div class="wrap">
    <form action='options.php' method='post'>
    <h1>Access & Whitelist Redirect Instellingen</h1>
    <?php
    settings_fields( 'pwr_plugin_page' );
    do_settings_sections( 'pwr_plugin_page' );
    submit_button( 'Wijzigingen opslaan' );
    ?>
    </form>
    </div>
    <?php
}
