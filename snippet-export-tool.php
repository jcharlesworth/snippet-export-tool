<?php
/**
 * Plugin Name: Snippet Export Tool (TEMPORARY)
 * Description: One-time tool to export all [raw_html_snippet id="..."] content while the original Raw HTML Snippets plugin is still active. Adds Tools > Export HTML Snippets. Delete this plugin after use.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', function () {
    add_management_page(
        'Export HTML Snippets',
        'Export HTML Snippets',
        'manage_options',
        'shs-export',
        'shs_export_page'
    );
} );

function shs_export_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.' );
    }

    echo '<div class="wrap"><h1>Export HTML Snippets</h1>';

    // Step 1: find every distinct snippet id referenced anywhere in post content.
    global $wpdb;
    $like = '%[raw_html_snippet%';
    $rows = $wpdb->get_col(
        $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE post_content LIKE %s", $like )
    );

    $ids = array();
    foreach ( $rows as $content ) {
        if ( preg_match_all( '/\[raw_html_snippet\s+id=["\']([^"\']+)["\']\s*\]/i', $content, $matches ) ) {
            foreach ( $matches[1] as $id ) {
                $ids[ $id ] = true;
            }
        }
    }
    $ids = array_keys( $ids );

    if ( empty( $ids ) ) {
        echo '<p><strong>No [raw_html_snippet ...] shortcodes were found in any post/page content.</strong></p>';
        echo '<p>If you have snippets that aren\'t currently used anywhere in content, list their IDs manually below (one per line) and click Export again.</p>';
        echo '<form method="post"><textarea name="manual_ids" rows="10" style="width:100%;" placeholder="my-snippet-1&#10;my-snippet-2"></textarea>';
        wp_nonce_field( 'shs_export_manual' );
        echo '<p><button class="button button-primary" name="shs_manual_submit" value="1">Export these IDs</button></p></form>';

        if ( isset( $_POST['shs_manual_submit'] ) && check_admin_referer( 'shs_export_manual' ) ) {
            $manual = sanitize_textarea_field( wp_unslash( $_POST['manual_ids'] ) );
            $ids    = array_filter( array_map( 'trim', explode( "\n", $manual ) ) );
        } else {
            echo '</div>';
            return;
        }
    }

    echo '<p>Found ' . count( $ids ) . ' distinct snippet ID(s) referenced in your content:</p><ul>';
    foreach ( $ids as $id ) {
        echo '<li><code>' . esc_html( $id ) . '</code></li>';
    }
    echo '</ul>';

    // Step 2: run each through do_shortcode() so the ORIGINAL plugin resolves it,
    // regardless of how it stores the data internally.
    $export = array();
    foreach ( $ids as $id ) {
        $shortcode = '[raw_html_snippet id="' . $id . '"]';
        $output    = do_shortcode( $shortcode );
        $export[]  = array(
            'id'      => $id,
            'content' => $output,
        );
    }

    $json = wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

    echo '<h2>Export result</h2>';
    echo '<p>Copy everything in the box below and save it as <code>snippets-export.json</code>. You will upload/paste this into the import tool after switching plugins.</p>';
    echo '<textarea readonly style="width:100%; min-height:400px; font-family:monospace;">' . esc_textarea( $json ) . '</textarea>';

    echo '<p><strong>Sanity check:</strong> compare the count above (' . count( $ids ) . ') to your actual snippet count (~30) in the old plugin\'s admin list. If it\'s lower, some snippets aren\'t referenced in any post content yet — add their IDs manually using the box further up and re-run.</p>';

    echo '</div>';
}