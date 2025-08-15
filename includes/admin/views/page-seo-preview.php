<?php
/**
 * View-Datei für die SEO-Vorschau-Seite.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direkten Zugriff verhindern
}
?>
<div class="wrap">
    <div class="csv-dashboard-header">
        <h1>🔍 CSV Import SEO-Vorschau</h1>
        <p>Analysieren und optimieren Sie die Darstellung Ihrer importierten Seiten in Suchmaschinen.</p>
    </div>

    <div class="csv-import-dashboard">
        <div class="csv-import-box" style="grid-column: 1 / -1;">
            <h3>
                <span class="step-icon">📊</span>
                Live-Vorschau & Analyse
            </h3>
            <p>Diese Vorschau nutzt die Daten aus Ihrer zuletzt validierten CSV-Datei. Passen Sie die Spaltenzuordnung unten an, um die Vorschau zu aktualisieren.</p>
            
            <?php
            // Prüft, ob die SEO-Vorschau-Klasse existiert und rendert das Widget
            if ( class_exists('CSV_Import_SEO_Preview') ) {
                CSV_Import_SEO_Preview::render_preview_widget();
            } else {
                echo '<div class="notice notice-error"><p>Fehler: Die SEO-Vorschau-Komponente (CSV_Import_SEO_Preview) konnte nicht geladen werden.</p></div>';
            }
            ?>
        </div>

        <div class="csv-import-box" style="grid-column: 1 / -1;">
             <h3>
                <span class="step-icon">🔄</span>
                Spaltenzuordnung für SEO-Felder
            </h3>
            <p>Weisen Sie hier die Spalten Ihrer CSV-Datei den entsprechenden SEO-Feldern zu. Die Vorschau oben wird automatisch aktualisiert.</p>
            
            <form method="post">
                 <?php wp_nonce_field( 'csv_import_seo_mapping' ); ?>
                 <input type="hidden" name="action" value="save_seo_mapping">
                 
                 <table class="form-table compact-form">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="seo_title">SEO-Titel</label></th>
                            <td>
                                <input type="text" id="seo_title" name="seo_title" class="regular-text" placeholder="Spaltenname, z.B. 'post_title'">
                                <p class="description">Spalte für den Titel in den Suchergebnissen. Fällt auf "post_title" zurück, wenn leer.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="seo_description">Meta-Description</label></th>
                            <td>
                                <input type="text" id="seo_description" name="seo_description" class="regular-text" placeholder="Spaltenname, z.B. 'post_excerpt'">
                                <p class="description">Spalte für die Beschreibung in den Suchergebnissen.</p>
                            </td>
                        </tr>
                    </tbody>
                 </table>

                 <div class="action-buttons" style="margin-top: 10px;">
                    <?php submit_button( 'Zuordnung speichern', 'primary', 'save_seo_mapping', false ); ?>
                </div>
            </form>
        </div>
    </div>
</div>
