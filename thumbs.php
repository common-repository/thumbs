<?php
/**
 * Plugin Name:       Thumbs
 * Description:       Ein einfaches Tool zur Verwaltung von Thumbnail-Dateien in WordPress. Zeigt die Anzahl der Thumbnails, die gelöscht werden können, und ermöglicht es, diese zu löschen oder eine Liste der betreffenden Dateien anzuzeigen.
 * Version:           1.0
 * Author:            Sebastian Rieder
 * Author URI:        https://www.zeilenhoehe.de/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

add_action('admin_menu', 'thumbs_menu');

function thumbs_menu() {
    add_menu_page('Thumbs', 'Thumbs', 'manage_options', 'thumbs', 'thumbs_admin_page');
}

function thumbs_admin_page() {
    $upload_dir = wp_upload_dir()['basedir'];
    $delete_count = 0;
    $file_list = [];
    $message = '';
    $empty_folders_count = 0;
    $nonce_action = 'thumbs_nonce_action';

    // Verarbeitung der POST-Anfragen
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (check_admin_referer($nonce_action, 'thumbs_nonce')) {
            if (isset($_POST['delete']) && current_user_can('manage_options')) {
                myplugin_delete_thumbnails($upload_dir, $delete_count);
                $message = "$delete_count Thumbnails wurden gelöscht.";
                echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>"; // Automatische Aktualisierung
            } elseif (isset($_POST['show_files']) && current_user_can('manage_options')) {
                myplugin_list_thumbnails($upload_dir, $file_list);
            } elseif (isset($_POST['delete_empty']) && current_user_can('manage_options')) {
                $deleted_folders_count = myplugin_delete_empty_folders_recursive($upload_dir);
                $message = "$deleted_folders_count leere Ordner wurden gelöscht.";
                echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>"; // Automatische Aktualisierung
            }
        }
    }

    // Zählen der Thumbnails und leeren Ordner
    myplugin_count_thumbnails($upload_dir, $delete_count);
    $empty_folders = myplugin_find_empty_folders($upload_dir);
    $empty_folders_count = count($empty_folders);

    $message = $delete_count > 0 ? "Es würden $delete_count Thumbnails gelöscht werden." : "Es sind keine Thumbnails zum Löschen vorhanden.";

    echo '<div class="wrap">';
    echo "<h1>Thumbs</h1>";
        
    // Plugin Beschreibung und Vorsichtsmaßnahmen
    echo '<div style="margin-bottom: 20px;">';
    echo '<h2>Beschreibung</h2>';
    echo '<p>Das Thumbs Plugin ermöglicht es Ihnen, Thumbnails auf Ihrer WordPress-Website effizient zu verwalten. Sie können die Anzahl der Thumbnails anzeigen, die gelöscht werden könnten, diese tatsächlich löschen oder eine Liste der betreffenden Dateien anzeigen.</p>';
    echo '<h2>Vorsichtsmaßnahmen</h2>';
    echo '<ul>';
    echo '<li>Stellen Sie sicher, dass Sie ein Backup Ihrer Website erstellen, bevor Sie Thumbnails löschen.</li>';
    echo '<li>Verwenden Sie dieses Tool mit Vorsicht, da das Löschen von Dateien irreversible Änderungen verursachen kann.</li>';
    echo '<li>Testen Sie das Plugin in einer Staging-Umgebung, bevor Sie es auf einer Live-Website verwenden.</li>';
    echo '</ul>';
    echo '</div>';
    
    // Hinweis zur Regenerierung von Thumbnails
    echo '<div style="margin-top: 20px;">';
    echo '<h2>Thumbnail-Regenerierung</h2>';
    echo '<p>Empfehlung: Für die Regenerierung von Thumbnails auf Ihrer Website empfehle ich zwei Methoden:</p>';
    echo '<ul>';
    echo '<li><strong>Verwendung von WooCommerce:</strong> Nutzen Sie die eingebaute Thumbnail-Regenerierungsfunktion von WooCommerce, falls Sie dieses E-Commerce-Plugin verwenden.</li>';
    echo '<li><strong>Plugin "Regenerate Thumbnails":</strong> Für eine umfassendere Lösung empfehle ich das Plugin "Regenerate Thumbnails". Dieses Plugin ermöglicht es Ihnen, alle Thumbnails auf Ihrer Website neu zu generieren.</li>';
    echo '</ul>';
    echo '</div>';

    // Anzahl der zu löschenden Bilder und Ordner
    echo '<p>Anzahl der möglichen zu löschenden Bilder: ' . esc_html($delete_count) . '</p>';
    echo '<p>Anzahl der möglichen zu löschenden Ordner: ' . esc_html($empty_folders_count) . '</p>';

    // Anzeige für leere Ordner
    if (!empty($empty_folders)) {
        echo '<div style="margin-bottom: 20px;">';
        echo "<p>Leere Ordner wurden gelöscht.</p>";
        echo '</div>';
    }

    // Buttons mit CSS-Margins
    echo '<form method="post">';
    wp_nonce_field($nonce_action, 'thumbs_nonce');
    echo '<input type="submit" name="delete" class="button button-primary" value="Thumbnails löschen" style="margin-right: 10px;">';
    echo '<input type="submit" name="show_files" class="button button-secondary" value="Dateien anzeigen" style="margin-right: 10px;">';
    echo '<input type="submit" name="delete_empty" class="button button-secondary" value="Leere Ordner löschen">';
    echo '</form>';    

    // Anzeigen der Dateien
    if (isset($_POST['show_files'])) {
        myplugin_list_thumbnails($upload_dir, $file_list);
        echo '<div style="margin-top: 20px;">';
        echo "<h2>Liste der Thumbnails:</h2>";
        if (!empty($file_list)) {
            echo "<ul>";
            foreach ($file_list as $file) {
                echo '<li>' . esc_html($file) . '</li>';
            }
            echo "</ul>";
        } else {
            echo "<p>Keine Thumbnails gefunden.</p>";
        }
        echo '</div>';
    }
    
   // Footer mit Autor-Informationen und Thumbnail-Regenerierungshinweis
    echo '<div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd;">';
    echo '<p>Realisiert von <a href="https://zeilenhoehe.de/" target="_blank">zeilenhoehe.de</a></p>';
    echo '</div>';
}

function myplugin_count_thumbnails($dir, &$count = 0) {
    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_dir($file)) {
            myplugin_count_thumbnails($file, $count);
        } else {
            if (preg_match('/-\d+x\d+\./', $file)) {
                $count++;
            }
        }
    }
}

function myplugin_delete_thumbnails($dir, &$count = 0) {
    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_dir($file)) {
            myplugin_delete_thumbnails($file, $count);
        } else {
            if (preg_match('/-\d+x\d+\./', $file)) {
                unlink($file);
                $count++;
            }
        }
    }
}

function myplugin_list_thumbnails($dir, &$file_list) {
    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_dir($file)) {
            myplugin_list_thumbnails($file, $file_list);
        } else {
            if (preg_match('/-\d+x\d+\./', $file)) {
                $file_list[] = $file;
            }
        }
    }
}

function myplugin_find_empty_folders($dir) {
    $empty_folders = [];
    $dirs = glob($dir . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $subdirs = myplugin_find_empty_folders($dir);
        if (count(glob($dir . '/*')) === 0 && empty($subdirs)) {
            $empty_folders[] = $dir;
        }
    }
    return $empty_folders;
}

function myplugin_delete_empty_folders_recursive($dir) {
    $empty_folders = myplugin_find_empty_folders($dir);
    $deleted_folders_count = 0;
    foreach ($empty_folders as $empty_folder) {
        rmdir($empty_folder);
        $deleted_folders_count++;
    }
    return $deleted_folders_count;
}
?>