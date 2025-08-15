<?php
/**
 * SEO Preview Integration für CSV Import Pro Plugin
 * Fügen Sie diese Dateien zu Ihrem Plugin hinzu
 */

// 1. NEUE DATEI: includes/admin/class-seo-preview.php
class CSV_Import_SEO_Preview {
    
    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_csv_seo_preview_validate', [__CLASS__, 'ajax_validate_seo']);
    }
    
    /**
     * Lädt CSS/JS für SEO Preview
     */
    public static function enqueue_assets($hook) {
        if (strpos($hook, 'csv-import') === false) return;
        
        wp_enqueue_style(
            'csv-seo-preview',
            CSV_IMPORT_PRO_URL . 'assets/css/seo-preview.css',
            [],
            CSV_IMPORT_PRO_VERSION
        );
        
        wp_enqueue_script(
            'csv-seo-preview',
            CSV_IMPORT_PRO_URL . 'assets/js/seo-preview.js',
            ['jquery'],
            CSV_IMPORT_PRO_VERSION,
            true
        );
        
        wp_localize_script('csv-seo-preview', 'csvSeoPreview', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('csv_seo_preview'),
            'domain' => home_url(),
            'strings' => [
                'title_too_long' => 'Titel zu lang (max. 60 Zeichen)',
                'desc_too_long' => 'Description zu lang (max. 160 Zeichen)',
                'excellent_seo' => 'Ausgezeichnet',
                'good_seo' => 'Gut',
                'needs_improvement' => 'Verbesserungswürdig'
            ]
        ]);
    }
    
    /**
     * AJAX Handler für SEO-Validierung
     */
    public static function ajax_validate_seo() {
        check_ajax_referer('csv_seo_preview', 'nonce');
        
        $title = sanitize_text_field($_POST['title'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $url_slug = sanitize_title($_POST['slug'] ?? '');
        
        $validation = self::validate_seo_data($title, $description, $url_slug);
        
        wp_send_json_success($validation);
    }
    
    /**
     * Validiert SEO-Daten und gibt Empfehlungen zurück
     */
    public static function validate_seo_data($title, $description, $slug = '') {
        $title_length = mb_strlen($title);
        $desc_length = mb_strlen($description);
        $url = home_url($slug);
        
        $validation = [
            'title' => [
                'length' => $title_length,
                'status' => self::get_length_status($title_length, 60, 70),
                'preview' => $title
            ],
            'description' => [
                'length' => $desc_length,
                'status' => self::get_length_status($desc_length, 160, 180),
                'preview' => $description
            ],
            'url' => [
                'full_url' => $url,
                'display_url' => parse_url($url, PHP_URL_HOST) . '/' . $slug
            ],
            'seo_score' => self::calculate_seo_score($title, $description),
            'recommendations' => self::get_seo_recommendations($title, $description)
        ];
        
        return $validation;
    }
    
    /**
     * Berechnet SEO-Score (0-100)
     */
    private static function calculate_seo_score($title, $description) {
        $score = 0;
        $title_length = mb_strlen($title);
        $desc_length = mb_strlen($description);
        
        // Titel-Länge (25 Punkte)
        if ($title_length >= 30 && $title_length <= 60) {
            $score += 25;
        } elseif ($title_length <= 70) {
            $score += 15;
        }
        
        // Description-Länge (25 Punkte)
        if ($desc_length >= 120 && $desc_length <= 160) {
            $score += 25;
        } elseif ($desc_length <= 180) {
            $score += 15;
        }
        
        // Titel-Struktur (20 Punkte)
        if (preg_match('/[|\-–—]/', $title)) $score += 10; // Separator
        if (str_word_count($title) >= 4) $score += 10; // Ausreichend Wörter
        
        // Description-Qualität (20 Punkte)
        if (str_word_count($description) >= 15) $score += 10; // Ausreichend Wörter
        if (preg_match('/[.!?]/', $description)) $score += 5; // Satzzeichen
        if (preg_match('/\b(kostenlos|gratis|neu|premium|professionell)\b/i', $description)) $score += 5;
        
        // Keywords (10 Punkte)
        $title_words = array_map('strtolower', explode(' ', $title));
        $desc_words = array_map('strtolower', explode(' ', $description));
        $common_words = array_intersect($title_words, $desc_words);
        if (count($common_words) >= 2) $score += 10;
        
        return min(100, $score);
    }
    
    /**
     * Gibt Längen-Status zurück
     */
    private static function get_length_status($length, $ideal_max, $absolute_max) {
        if ($length <= $ideal_max) {
            return 'good';
        } elseif ($length <= $absolute_max) {
            return 'warning';
        } else {
            return 'bad';
        }
    }
    
    /**
     * Gibt SEO-Verbesserungsvorschläge zurück
     */
    private static function get_seo_recommendations($title, $description) {
        $recommendations = [];
        
        $title_length = mb_strlen($title);
        $desc_length = mb_strlen($description);
        
        if ($title_length < 30) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Titel ist zu kurz. Fügen Sie beschreibende Wörter hinzu.',
                'field' => 'title'
            ];
        }
        
        if ($title_length > 60) {
            $recommendations[] = [
                'type' => 'error',
                'message' => 'Titel wird in Suchergebnissen abgeschnitten. Kürzen Sie ihn.',
                'field' => 'title'
            ];
        }
        
        if ($desc_length < 120) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Description könnte ausführlicher sein für bessere CTR.',
                'field' => 'description'
            ];
        }
        
        if ($desc_length > 160) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Description wird möglicherweise gekürzt dargestellt.',
                'field' => 'description'
            ];
        }
        
        if (!preg_match('/[|\-–—]/', $title)) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Erwägen Sie einen Separator (|, -) für bessere Struktur.',
                'field' => 'title'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Rendert die SEO Preview in Admin-Seiten
     */
    public static function render_preview_widget($csv_data = []) {
        ?>
        <div class="csv-seo-preview-widget">
            <h3>
                🔍 Live SEO Vorschau 
                <span class="seo-live-indicator">LIVE</span>
            </h3>
            
            <div class="seo-engine-tabs">
                <button class="seo-tab active" data-engine="google">Google</button>
                <button class="seo-tab" data-engine="bing">Bing</button>
            </div>
            
            <div class="seo-device-tabs">
                <button class="device-tab active" data-device="desktop">🖥️ Desktop</button>
                <button class="device-tab" data-device="mobile">📱 Mobile</button>
            </div>
            
            <div class="seo-preview-container">
                <!-- Google SERP Preview -->
                <div class="serp-preview google-serp active">
                    <div class="serp-result">
                        <div class="serp-title" id="google-title-preview">
                            <?php echo esc_html($csv_data['seo_title'] ?? $csv_data['post_title'] ?? 'Ihr Seitentitel'); ?>
                        </div>
                        <div class="serp-url" id="google-url-preview">
                            <?php echo esc_url(home_url('/beispiel-seite')); ?>
                        </div>
                        <div class="serp-description" id="google-desc-preview">
                            <?php echo esc_html($csv_data['seo_description'] ?? 'Ihre Meta-Description erscheint hier...'); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Bing SERP Preview -->
                <div class="serp-preview bing-serp">
                    <div class="serp-result">
                        <div class="serp-title bing-style" id="bing-title-preview">
                            <?php echo esc_html($csv_data['seo_title'] ?? $csv_data['post_title'] ?? 'Ihr Seitentitel'); ?>
                        </div>
                        <div class="serp-url bing-style" id="bing-url-preview">
                            <?php echo esc_url(home_url('/beispiel-seite')); ?>
                        </div>
                        <div class="serp-description bing-style" id="bing-desc-preview">
                            <?php echo esc_html($csv_data['seo_description'] ?? 'Ihre Meta-Description erscheint hier...'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="seo-metrics">
                <h4>📊 SEO Metriken</h4>
                <div class="metric-row">
                    <span>Titel-Länge:</span>
                    <span class="metric-value" id="title-length-metric">0 Zeichen</span>
                </div>
                <div class="metric-row">
                    <span>Description-Länge:</span>
                    <span class="metric-value" id="desc-length-metric">0 Zeichen</span>
                </div>
                <div class="metric-row">
                    <span>SEO-Score:</span>
                    <span class="metric-value" id="seo-score-metric">Berechnung...</span>
                </div>
            </div>
            
            <div class="seo-recommendations" id="seo-recommendations">
                <!-- Dynamische Empfehlungen werden hier eingefügt -->
            </div>
        </div>
        <?php
    }
}
