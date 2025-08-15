<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direkten Zugriff auf die Datei verhindern
}

/**
 * Erstellt die Admin-Menüs und steuert die Anzeige der Plugin-Seiten.
 * Version 8.3 - Erweiterte SEO-Vorschau Integration
 * @since 6.0
 */
class CSV_Import_Pro_Admin {

	private $menu_slug = 'csv-import';
	private $admin_pages = [];

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'admin_init', [ $this, 'register_plugin_settings' ] );
        add_action( 'admin_notices', [ $this, 'show_plugin_notices' ] );
        
        // SEO-Vorschau Integration initialisieren
        $this->init_seo_preview_integration();
	}

	/**
	 * Initialisiert die SEO-Vorschau Integration
	 * @since 8.3
	 */
	private function init_seo_preview_integration() {
		// SEO-Vorschau-Klasse initialisieren falls verfügbar
		if (class_exists('CSV_Import_SEO_Preview')) {
			CSV_Import_SEO_Preview::init();
		}
		
		// Hook für SEO-Vorschau in Validierungsergebnissen
		add_action('csv_import_after_validation_display', [$this, 'add_seo_preview_to_validation']);
		
		// Hook für SEO-Vorschau in der Hauptseite
		add_action('csv_import_main_page_after_upload', [$this, 'render_seo_preview_section']);
	}

	public function register_admin_menu() {
        // Hauptseite unter "Werkzeuge" hinzufügen
        $main_page_hook = add_management_page(
            __( 'CSV Import Pro', 'csv-import' ),
            __( 'CSV Import', 'csv-import' ),
            'edit_pages',
            $this->menu_slug,
            [ $this, 'display_main_page' ]
        );
        $this->admin_pages['main'] = $main_page_hook;

        // Untermenüs hinzufügen
        $submenus = [
            'settings' => [
                'page_title' => __( 'CSV Import Einstellungen', 'csv-import' ),
                'menu_title' => __( 'Einstellungen', 'csv-import' ),
                'capability' => 'edit_pages',
                'menu_slug'  => 'csv-import-settings',
                'callback'   => [ $this, 'display_settings_page' ]
            ],
            'backups' => [
                'page_title' => __( 'CSV Import Backups', 'csv-import' ),
                'menu_title' => __( 'Backups & Rollback', 'csv-import' ),
                'capability' => 'edit_pages',
                'menu_slug'  => 'csv-import-backups',
                'callback'   => [ $this, 'display_backup_page' ]
            ],
            'profiles' => [
                'page_title' => __( 'CSV Import Profile', 'csv-import' ),
                'menu_title' => __( 'Import-Profile', 'csv-import' ),
                'capability' => 'edit_pages',
                'menu_slug'  => 'csv-import-profiles',
                'callback'   => [ $this, 'display_profiles_page' ]
            ],
            'scheduling' => [
                'page_title' => __( 'CSV Import Automatisierung', 'csv-import' ),
                'menu_title' => __( 'Automatisierung', 'csv-import' ),
                'capability' => 'manage_options', // Nur für Admins
                'menu_slug'  => 'csv-import-scheduling',
                'callback'   => [ $this, 'display_scheduling_page' ]
            ],
            'logs' => [
                'page_title' => __( 'CSV Import Logs', 'csv-import' ),
                'menu_title' => __( 'Logs & Monitoring', 'csv-import' ),
                'capability' => 'edit_pages',
                'menu_slug'  => 'csv-import-logs',
                'callback'   => [ $this, 'display_logs_page' ]
            ],
            'seo_preview' => [
                'page_title' => __( 'CSV Import SEO-Vorschau', 'csv-import' ),
                'menu_title' => __( 'SEO-Vorschau', 'csv-import' ),
                'capability' => 'edit_pages',
                'menu_slug'  => 'csv-import-seo-preview',
                'callback'   => [ $this, 'display_seo_preview_page' ]
            ]
        ];

        // Das erste Untermenü muss der Hauptseite entsprechen, aber wir verstecken es nicht
        add_submenu_page(
            'tools.php',
            __( 'CSV Import Dashboard', 'csv-import' ),
            __( 'Import Dashboard', 'csv-import' ),
            'edit_pages',
            $this->menu_slug,
            [ $this, 'display_main_page' ]
        );

        foreach ( $submenus as $key => $submenu ) {
            $submenu_hook = add_submenu_page(
                'tools.php', // Alle unter "Werkzeuge"
                $submenu['page_title'],
                $submenu['menu_title'],
                $submenu['capability'],
                $submenu['menu_slug'],
                $submenu['callback']
            );
            $this->admin_pages[$key] = $submenu_hook;
        }
	}

    public function display_main_page() { $this->render_page('page-main.php'); }
    public function display_settings_page() { $this->render_page('page-settings.php'); }
    public function display_backup_page() { $this->render_page('page-backups.php'); }
    public function display_profiles_page() { $this->render_page('page-profiles.php'); }
    public function display_scheduling_page() { $this->render_page('page-scheduling.php'); }
    public function display_logs_page() { $this->render_page('page-logs.php'); }
    public function display_seo_preview_page() { $this->render_page('page-seo-preview.php'); }

    /**
     * Rendert eine Template-Datei mit allen benötigten Daten
     * Version 8.3 - Erweiterte Scheduler-Integration + SEO-Vorschau
     */
    private function render_page($template_file) {
        $data = []; // Basis-Daten-Array
        
        // === GRUNDLEGENDE DATEN ===
        
        // Progress-Daten
        if (function_exists('csv_import_get_progress')) {
            $data['progress'] = csv_import_get_progress();
        }
        
        // Plugin-Konfiguration
        if (function_exists('csv_import_get_config')) {
            $config = csv_import_get_config();
            $data['config'] = $config;
            
            // Konfiguration validieren
            if(function_exists('csv_import_validate_config')) {
                $data['config_valid'] = csv_import_validate_config($config);
            }
        }
        
        // System Health Check
        if (function_exists('csv_import_system_health_check')) {
            $data['health'] = csv_import_system_health_check();
        }
        
        // Import-Statistiken
        if (function_exists('csv_import_get_stats')) {
            $data['stats'] = csv_import_get_stats();
        }
        
        // Error-Statistiken
        if (function_exists('csv_import_get_error_stats')) {
            $data['error_stats'] = csv_import_get_error_stats();
        }
        
        // === SEO-VORSCHAU-SPEZIFISCHE DATEN ===
        
        if (class_exists('CSV_Import_SEO_Preview')) {
            // SEO-Vorschau-Daten für verschiedene Templates
            if ($template_file === 'page-seo-preview.php') {
                $data['seo_preview_data'] = $this->prepare_seo_preview_data();
            }
            
            // CSV-Daten für SEO-Vorschau wenn verfügbar
            if (function_exists('csv_import_get_last_parsed_data')) {
                $data['csv_sample_data'] = csv_import_get_last_parsed_data();
            }
            
            // SEO-Plugin-Kompatibilität prüfen
            $data['seo_plugin_compatibility'] = $this->check_seo_plugin_compatibility();
        }
        
        // === SCHEDULER-SPEZIFISCHE DATEN ===
        
        if (class_exists('CSV_Import_Scheduler')) {
            // Scheduler-Basis-Informationen
            if (method_exists('CSV_Import_Scheduler', 'get_scheduler_info')) {
                $scheduler_info = CSV_Import_Scheduler::get_scheduler_info();
                $data = array_merge($data, $scheduler_info);
                
                // Spezifische Template-Variablen für page-scheduling.php
                $data['is_scheduled'] = $scheduler_info['is_scheduled'] ?? false;
                $data['current_source'] = get_option('csv_import_scheduled_source', '');
                $data['current_frequency'] = get_option('csv_import_scheduled_frequency', '');
                $data['next_scheduled'] = $scheduler_info['next_run'] ?? false;
                
                // Zusätzliche Scheduler-Daten
                $data['available_intervals'] = $scheduler_info['available_intervals'] ?? [];
                $data['wp_cron_disabled'] = $scheduler_info['wp_cron_disabled'] ?? false;
            }
            
            // Source-Validation für Scheduling
            if (isset($data['config']) && function_exists('csv_import_validate_config')) {
                $validation = csv_import_validate_config($data['config']);
                $data['validation'] = $validation;
            }
            
            // Notification-Einstellungen
            $data['notification_settings'] = get_option('csv_import_notification_settings', [
                'email_on_success' => false,
                'email_on_failure' => true,
                'recipients' => [get_option('admin_email')]
            ]);
            
            // Scheduled Import History
            if (class_exists('CSV_Import_Error_Handler') && method_exists('CSV_Import_Error_Handler', 'get_persistent_errors')) {
                $all_logs = CSV_Import_Error_Handler::get_persistent_errors();
                
                // Filter für scheduled events
                $data['scheduled_imports'] = array_filter($all_logs, function($log) {
                    if (!is_array($log) || !isset($log['message'])) {
                        return false;
                    }
                    return stripos($log['message'], 'geplant') !== false || 
                           stripos($log['message'], 'scheduled') !== false ||
                           stripos($log['message'], 'automatisch') !== false;
                });
                
                // Sortiere nach Zeit (neueste zuerst)
                usort($data['scheduled_imports'], function($a, $b) {
                    return strtotime($b['time'] ?? '1970-01-01') - strtotime($a['time'] ?? '1970-01-01');
                });
                
                // Limitiere auf die letzten 20 Einträge
                $data['scheduled_imports'] = array_slice($data['scheduled_imports'], 0, 20);
            } else {
                $data['scheduled_imports'] = [];
            }
        }
        
        // === BACKUP-SPEZIFISCHE DATEN ===
        
        if (class_exists('CSV_Import_Backup_Manager') && method_exists('CSV_Import_Backup_Manager', 'get_import_sessions')) {
            $data['sessions'] = CSV_Import_Backup_Manager::get_import_sessions();
        }
        
        // === PROFILE-SPEZIFISCHE DATEN ===
        
        if (class_exists('CSV_Import_Profile_Manager') && method_exists('CSV_Import_Profile_Manager', 'get_profiles')) {
            $data['profiles'] = CSV_Import_Profile_Manager::get_profiles();
        }
        
        // === LOGS-SPEZIFISCHE DATEN ===
        
        if (class_exists('CSV_Import_Error_Handler') && method_exists('CSV_Import_Error_Handler', 'get_persistent_errors')) {
            $all_logs = CSV_Import_Error_Handler::get_persistent_errors();
            
            // Filter-Level aus GET-Parameter
            $filter_level = isset($_GET['level']) ? sanitize_key($_GET['level']) : 'all';
            
            // Logs filtern
            if ($filter_level !== 'all') {
                $filtered_logs = array_filter($all_logs, function($log) use ($filter_level) {
                    return isset($log['level']) && $log['level'] === $filter_level;
                });
            } else {
                $filtered_logs = $all_logs;
            }
            
            // Pagination für Logs
            $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $per_page = 50;
            $total_logs = count($filtered_logs);
            $total_pages = ceil($total_logs / $per_page);
            $offset = ($page - 1) * $per_page;
            
            $data['logs'] = array_slice($filtered_logs, $offset, $per_page);
            $data['filter_level'] = $filter_level;
            $data['page'] = $page;
            $data['total_pages'] = $total_pages;
            $data['total_logs'] = $total_logs;
        }
        
        // === FORM-HANDLING FÜR VERSCHIEDENE SEITEN ===
        
        // Scheduling-Form-Handler
        if ($template_file === 'page-scheduling.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = array_merge($data, $this->handle_scheduling_form());
        }
        
        // Backup-Form-Handler
        if ($template_file === 'page-backups.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = array_merge($data, $this->handle_backup_form());
        }
        
        // Profile-Form-Handler
        if ($template_file === 'page-profiles.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = array_merge($data, $this->handle_profile_form());
        }
        
        // Logs-Form-Handler
        if ($template_file === 'page-logs.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = array_merge($data, $this->handle_logs_form());
        }

        // SEO-Preview-Form-Handler
        if ($template_file === 'page-seo-preview.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = array_merge($data, $this->handle_seo_preview_form());
        }

        // === TEMPLATE RENDERN ===
        
        // Daten für Template verfügbar machen
        extract($data);

        $template_path = CSV_IMPORT_PRO_PATH . 'includes/admin/views/' . $template_file;
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            echo '<div class="wrap"><h2>Template-Datei nicht gefunden</h2><p>Die Datei ' . esc_html($template_file) . ' konnte nicht geladen werden.</p></div>';
        }
    }

    /**
     * Bereitet SEO-Vorschau-Daten vor
     * @since 8.3
     */
    private function prepare_seo_preview_data() {
        $seo_data = [
            'available_templates' => [],
            'sample_data' => [],
            'seo_fields_mapping' => []
        ];

        // Templates aus der Konfiguration holen
        if (function_exists('csv_import_get_config')) {
            $config = csv_import_get_config();
            $seo_data['current_template'] = $config['template_id'] ?? '';
            $seo_data['post_type'] = $config['post_type'] ?? 'post';
        }

        // Verfügbare SEO-Felder mappings
        $seo_data['seo_fields_mapping'] = [
            'title' => 'CSV-Spalte für SEO-Titel',
            'description' => 'CSV-Spalte für Meta-Description',
            'keywords' => 'CSV-Spalte für Keywords',
            'canonical_url' => 'CSV-Spalte für Canonical URL',
            'og_title' => 'CSV-Spalte für Open Graph Titel',
            'og_description' => 'CSV-Spalte für Open Graph Beschreibung'
        ];

        return $seo_data;
    }

    /**
     * Prüft SEO-Plugin-Kompatibilität
     * @since 8.3
     */
    private function check_seo_plugin_compatibility() {
        $compatibility = [
            'yoast_seo' => class_exists('WPSEO_Options'),
            'rankmath' => class_exists('RankMath'),
            'aioseo' => class_exists('AIOSEO\\Plugin\\AIOSEO'),
            'seopress' => function_exists('seopress_init')
        ];

        $compatibility['active_plugin'] = '';
        foreach ($compatibility as $plugin => $is_active) {
            if ($is_active && $plugin !== 'active_plugin') {
                $compatibility['active_plugin'] = $plugin;
                break;
            }
        }

        return $compatibility;
    }

    /**
     * Fügt SEO-Vorschau zu CSV-Validierungsergebnissen hinzu
     * @since 8.3
     */
   public function add_seo_preview_to_validation($csv_data) {
    if (empty($csv_data['data']) || !class_exists('CSV_Import_SEO_Preview')) {
        return;
    }

    // Erste Zeile für Preview verwenden
    $sample_row = $csv_data['data'][0] ?? [];

    if (empty($sample_row)) {
        return;
    }

    // Daten für die Vorschau vorbereiten
    $preview_data = [];
    $mapping = get_option('csv_import_seo_field_mapping', []);

    $preview_data['seo_title'] = !empty($mapping['title']) && isset($sample_row[$mapping['title']])
        ? $sample_row[$mapping['title']]
        : ($sample_row['post_title'] ?? $sample_row['title'] ?? '');

    $preview_data['seo_description'] = !empty($mapping['description']) && isset($sample_row[$mapping['description']])
        ? $sample_row[$mapping['description']]
        : ($sample_row['post_excerpt'] ?? $sample_row['excerpt'] ?? '');


    echo '<div class="csv-seo-integration" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">';
    echo '<h4 style="margin-top: 0; color: #0073aa;">🔍 SEO-Vorschau basierend auf Ihren CSV-Daten:</h4>';

    // SEO-Vorschau-Widget mit den Daten aus der CSV rendern
    CSV_Import_SEO_Preview::render_preview_widget($preview_data);

    echo '<p style="margin-bottom: 0; font-style: italic; color: #666;">💡 Diese Vorschau basiert auf der ersten Zeile Ihrer CSV-Daten. Für detailliertere SEO-Einstellungen besuchen Sie die <a href="' . admin_url('tools.php?page=csv-import-seo-preview') . '">SEO-Vorschau-Seite</a>.</p>';
    echo '</div>';
}
    /**
     * Rendert einfache SEO-Vorschau als Fallback
     * @since 8.3
     */
    private function render_simple_seo_preview($sample_row) {
        if (empty($sample_row)) {
            echo '<p>Keine Daten für Vorschau verfügbar.</p>';
            return;
        }

        echo '<div class="seo-preview-simple" style="border: 1px solid #ddd; padding: 10px; background: white; border-radius: 4px;">';
        echo '<h5 style="margin: 0 0 5px 0; color: #1e0fbe; font-size: 18px;">';
        
        // Titel aus verschiedenen möglichen Spalten
        $title_candidates = ['title', 'post_title', 'name', 'headline'];
        $title = '';
        foreach ($title_candidates as $candidate) {
            if (isset($sample_row[$candidate]) && !empty($sample_row[$candidate])) {
                $title = $sample_row[$candidate];
                break;
            }
        }
        
        echo esc_html($title ?: 'Beispiel-Titel aus CSV-Daten');
        echo '</h5>';
        
        echo '<p style="margin: 5px 0; color: #006621; font-size: 14px;">example.com/sample-url</p>';
        
        // Beschreibung aus verschiedenen möglichen Spalten
        $description_candidates = ['description', 'excerpt', 'summary', 'content'];
        $description = '';
        foreach ($description_candidates as $candidate) {
            if (isset($sample_row[$candidate]) && !empty($sample_row[$candidate])) {
                $description = $sample_row[$candidate];
                break;
            }
        }
        
        $description = wp_trim_words($description ?: 'Beispiel-Beschreibung basierend auf Ihren CSV-Daten. Diese wird automatisch aus Ihrem Inhalt generiert.', 20);
        echo '<p style="margin: 5px 0 0 0; color: #545454; font-size: 14px;">' . esc_html($description) . '</p>';
        echo '</div>';
    }

    /**
     * Rendert SEO-Vorschau-Sektion für Hauptseite
     * @since 8.3
     */
    public function render_seo_preview_section() {
        if (!class_exists('CSV_Import_SEO_Preview')) {
            return;
        }

        echo '<div class="csv-seo-main-section" style="margin-top: 20px;">';
        echo '<h3>SEO-Vorschau</h3>';
        echo '<p>Sehen Sie sich an, wie Ihre importierten Inhalte in Suchmaschinen erscheinen werden.</p>';
        echo '<p><a href="' . admin_url('tools.php?page=csv-import-seo-preview') . '" class="button button-secondary">SEO-Vorschau öffnen</a></p>';
        echo '</div>';
    }

    /**
     * Behandelt SEO-Preview-Form-Submissions
     * @since 8.3
     */
    private function handle_seo_preview_form() {
        $result = ['action_result' => null];
        
        if (!isset($_POST['action'])) {
            return $result;
        }
        
        $action = sanitize_key($_POST['action']);
        
        // SEO-Feldmapping speichern
        if ($action === 'save_seo_mapping') {
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'csv_import_seo_mapping')) {
                $result['action_result'] = [
                    'success' => false,
                    'message' => 'Sicherheitscheck fehlgeschlagen.'
                ];
                return $result;
            }
            
            $seo_mapping = [];
            $seo_fields = ['title', 'description', 'keywords', 'canonical_url', 'og_title', 'og_description'];
            
            foreach ($seo_fields as $field) {
                if (!empty($_POST['seo_' . $field])) {
                    $seo_mapping[$field] = sanitize_text_field($_POST['seo_' . $field]);
                }
            }
            
            update_option('csv_import_seo_field_mapping', $seo_mapping);
            
            $result['action_result'] = [
                'success' => true,
                'message' => 'SEO-Feldmapping erfolgreich gespeichert.'
            ];
        }
        
        return $result;
    }

    /**
     * Behandelt Scheduling-Form-Submissions
     */
    private function handle_scheduling_form() {
        $result = ['action_result' => null];
        
        if (!isset($_POST['action'])) {
            return $result;
        }
        
        $action = sanitize_key($_POST['action']);
        
        // Scheduling aktivieren
        if ($action === 'schedule_import') {
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'csv_import_scheduling')) {
                $result['action_result'] = [
                    'success' => false,
                    'message' => 'Sicherheitscheck fehlgeschlagen.'
                ];
                return $result;
            }
            
            $frequency = sanitize_key($_POST['frequency'] ?? '');
            $source = sanitize_key($_POST['import_source'] ?? '');
            
            if (empty($frequency) || empty($source)) {
                $result['action_result'] = [
                    'success' => false,
                    'message' => 'Frequenz und Quelle sind erforderlich.'
                ];
                return $result;
            }
            
            if (class_exists('CSV_Import_Scheduler')) {
                $schedule_result = CSV_Import_Scheduler::schedule_import($frequency, $source);
                
                if (is_wp_error($schedule_result)) {
                    $result['action_result'] = [
                        'success' => false,
                        'message' => 'Scheduling fehlgeschlagen: ' . $schedule_result->get_error_message()
                    ];
                } else {
                    $result['action_result'] = [
                        'success' => true,
                        'message' => 'Geplanter Import wurde erfolgreich aktiviert!'
                    ];
                }
            }
        }
        
        // Scheduling deaktivieren
        if ($action === 'unschedule_import') {
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'csv_import_scheduling')) {
                $result['action_result'] = [
                    'success' => false,
                    'message' => 'Sicherheitscheck fehlgeschlagen.'
                ];
                return $result;
            }
            
            if (class_exists('CSV_Import_Scheduler')) {
                CSV_Import_Scheduler::unschedule_import();
                $result['action_result'] = [
                    'success' => true,
                    'message' => 'Geplanter Import wurde deaktiviert.'
                ];
            }
        }
        
        // Notification-Einstellungen aktualisieren
        if ($action === 'update_notifications') {
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'csv_import_notification_settings')) {
                $result['action_result'] = [
                    'success' => false,
                    'message' => 'Sicherheitscheck fehlgeschlagen.'
                ];
                return $result;
            }
            
            $settings = [
                'email_on_success' => !empty($_POST['email_on_success']),
                'email_on_failure' => !empty($_POST['email_on_failure']),
                'recipients' => array_filter(
                    array_map('trim', explode("\n", $_POST['recipients'] ?? '')),
                    'is_email'
                )
            ];
            
            if (empty($settings['recipients'])) {
                $settings['recipients'] = [get_option('admin_email')];
            }
            
            update_option('csv_import_notification_settings', $settings);
            
            $result['action_result'] = [
                'success' => true,
                'message' => 'Benachrichtigungseinstellungen gespeichert.'
            ];
        }
        
        return $result;
    }

    /**
     * Behandelt Backup-Form-Submissions
     */
    private function handle_backup_form() {
        $result = [];
        
        if (!isset($_POST['action'])) {
            return $result;
        }
        
        // Rollback durchführen
        if (isset($_POST['rollback_session'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'csv_import_rollback')) {
                return $result;
            }
            
            $session_id = sanitize_text_field($_POST['rollback_session']);
            
            if (class_exists('CSV_Import_Backup_Manager')) {
                $rollback_result = CSV_Import_Backup_Manager::rollback_import($session_id);
                $result['rollback_result'] = $rollback_result;
            }
        }
        
        // Alte Backups bereinigen
        if (isset($_POST['cleanup_backups'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'csv_import_cleanup_backups')) {
                return $result;
            }
            
            if (class_exists('CSV_Import_Backup_Manager')) {
                $advanced_settings = get_option('csv_import_advanced_settings', ['backup_retention_days' => 30]);
                $retention_days = $advanced_settings['backup_retention_days'] ?? 30;
                $deleted_count = CSV_Import_Backup_Manager::cleanup_old_backups($retention_days);
                $result['deleted_count'] = $deleted_count;
            }
        }
        
        return $result;
    }

    /**
     * Behandelt Profile-Form-Submissions
     */
    private function handle_profile_form() {
        $result = [];
        
        if (!isset($_POST['action'])) {
            return $result;
        }
        
        $action = sanitize_key($_POST['action']);
        
        // Profil speichern
        if ($action === 'save_profile') {
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'csv_import_save_profile')) {
                $result['action_result'] = [
                    'success' => false,
                    'message' => 'Sicherheitscheck fehlgeschlagen.'
                ];
                return $result;
            }
            
            $profile_name = sanitize_text_field($_POST['profile_name'] ?? '');
            if (empty($profile_name)) {
                $result['action_result'] = [
                    'success' => false,
                    'message' => 'Profil-Name ist erforderlich.'
                ];
                return $result;
            }
            
            if (class_exists('CSV_Import_Profile_Manager') && function_exists('csv_import_get_config')) {
                $config = csv_import_get_config();
                $profile_id = CSV_Import_Profile_Manager::save_profile($profile_name, $config);
                
                $result['action_result'] = [
                    'success' => true,
                    'message' => "Profil '{$profile_name}' erfolgreich gespeichert."
                ];
            }
        }
        
        // Profil laden
        if ($action === 'load_profile') {
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'csv_import_load_profile')) {
                $result['action_result'] = [
                    'success' => false,
                    'message' => 'Sicherheitscheck fehlgeschlagen.'
                ];
                return $result;
            }
            
            $profile_id = sanitize_key($_POST['profile_id'] ?? '');
            
            if (class_exists('CSV_Import_Profile_Manager')) {
                $success = CSV_Import_Profile_Manager::load_profile($profile_id);
                
                if ($success) {
                    $result['action_result'] = [
                        'success' => true,
                        'message' => 'Profil erfolgreich geladen. Konfiguration wurde aktualisiert.'
                    ];
                } else {
                    $result['action_result'] = [
                        'success' => false,
                        'message' => 'Profil konnte nicht geladen werden.'
                    ];
                }
            }
        }
        
        // Profil löschen
        if ($action === 'delete_profile') {
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'csv_import_delete_profile')) {
                $result['action_result'] = [
                    'success' => false,
                    'message' => 'Sicherheitscheck fehlgeschlagen.'
                ];
                return $result;
            }
            
            $profile_id = sanitize_key($_POST['profile_id'] ?? '');
            
            if (class_exists('CSV_Import_Profile_Manager')) {
                $success = CSV_Import_Profile_Manager::delete_profile($profile_id);
                
                if ($success) {
                    $result['action_result'] = [
                        'success' => true,
                        'message' => 'Profil erfolgreich gelöscht.'
                    ];
                } else {
                    $result['action_result'] = [
                        'success' => false,
                        'message' => 'Profil konnte nicht gelöscht werden.'
                    ];
                }
            }
        }
        
        return $result;
    }

    /**
     * Behandelt Logs-Form-Submissions
     */
    private function handle_logs_form() {
        $result = [];
        
        if (!isset($_POST['action'])) {
            return $result;
        }
        
        // Logs löschen
        if ($_POST['action'] === 'clear_logs') {
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'csv_import_clear_logs')) {
                return $result;
            }
            
            if (class_exists('CSV_Import_Error_Handler')) {
                CSV_Import_Error_Handler::clear_error_log();
                
                // Redirect um doppelte Form-Submission zu vermeiden
                wp_redirect(add_query_arg('logs_cleared', 'true', remove_query_arg('_wpnonce')));
                exit;
            }
        }
        
        return $result;
    }

    public function enqueue_admin_assets($hook_suffix) {
        if (strpos($hook_suffix, 'csv-import') === false) {
            return;
        }
        
        wp_enqueue_style(
            'csv-import-pro-admin-style',
            CSV_IMPORT_PRO_URL . "assets/css/admin-style.css",
            [],
            CSV_IMPORT_PRO_VERSION
        );
        
        // SEO-spezifische Styles für SEO-Vorschau-Seite
        if (isset($_GET['page']) && $_GET['page'] === 'csv-import-seo-preview') {
            wp_enqueue_style(
                'csv-import-seo-preview-style',
                CSV_IMPORT_PRO_URL . "assets/css/seo-preview.css",
                ['csv-import-pro-admin-style'],
                CSV_IMPORT_PRO_VERSION
            );
        }
        
        wp_enqueue_script(
            'csv-import-pro-admin-script',
            CSV_IMPORT_PRO_URL . "assets/js/admin-script.js",
            ['jquery'],
            CSV_IMPORT_PRO_VERSION,
            true
        );
        
        // SEO-spezifische Scripts
        if (isset($_GET['page']) && $_GET['page'] === 'csv-import-seo-preview') {
            wp_enqueue_script(
                'csv-import-seo-preview-script',
                CSV_IMPORT_PRO_URL . "assets/js/seo-preview.js",
                ['jquery', 'csv-import-pro-admin-script'],
                CSV_IMPORT_PRO_VERSION,
                true
            );
        }
        
        // AJAX-Daten mit erweiterten Debug-Informationen und SEO-Daten
        $ajax_data = [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('csv_import_ajax'),
            'debug'   => defined('WP_DEBUG') && WP_DEBUG,
            'import_running' => function_exists('csv_import_is_import_running') ? csv_import_is_import_running() : false,
            'plugin_version' => CSV_IMPORT_PRO_VERSION
        ];
        
        // SEO-spezifische AJAX-Daten hinzufügen
        if (class_exists('CSV_Import_SEO_Preview')) {
            $ajax_data['seo_preview_enabled'] = true;
            $ajax_data['seo_field_mapping'] = get_option('csv_import_seo_field_mapping', []);
            $ajax_data['seo_plugin_compatibility'] = $this->check_seo_plugin_compatibility();
        }
        
        wp_localize_script('csv-import-pro-admin-script', 'csvImportAjax', $ajax_data);
    }
    
    /**
     * Plugin-Einstellungen registrieren
     * Version 8.3 - Erweiterte SEO-Einstellungen
     */
    public function register_plugin_settings() {
        $settings = [
            'template_id', 'post_type', 'post_status', 'page_builder', 'dropbox_url', 
            'local_path', 'image_source', 'image_folder', 'memory_limit', 'time_limit', 
            'seo_plugin', 'required_columns', 'skip_duplicates', 'delimiter',
            'noindex_posts' // KORREKTUR: Neue Option hier registriert
        ];
        
        // SEO-spezifische Einstellungen hinzufügen
        $seo_settings = [
            'seo_field_mapping',           // Feldmapping für SEO-Daten
            'seo_auto_generate_meta',      // Automatische Meta-Generierung
            'seo_default_meta_template',   // Standard-Template für Meta-Beschreibungen
            'seo_enable_og_tags',          // Open Graph Tags aktivieren
            'seo_enable_twitter_cards',    // Twitter Cards aktivieren
            'seo_canonical_base_url'       // Basis-URL für Canonical URLs
        ];
        
        $all_settings = array_merge($settings, $seo_settings);
        
        foreach ($all_settings as $setting) {
            register_setting('csv_import_settings', 'csv_import_' . $setting);
        }
        
        // SEO-spezifische Einstellungsgruppe
        register_setting('csv_import_seo_settings', 'csv_import_seo_field_mapping');
        register_setting('csv_import_seo_settings', 'csv_import_seo_auto_generate_meta');
        register_setting('csv_import_seo_settings', 'csv_import_seo_default_meta_template');
        register_setting('csv_import_seo_settings', 'csv_import_seo_enable_og_tags');
        register_setting('csv_import_seo_settings', 'csv_import_seo_enable_twitter_cards');
        register_setting('csv_import_seo_settings', 'csv_import_seo_canonical_base_url');
    }
    
    /**
     * Plugin-Benachrichtigungen anzeigen
     * Version 8.3 - Erweiterte SEO-Benachrichtigungen
     */
    public function show_plugin_notices() {
        if (isset($_GET['page']) && strpos($_GET['page'], 'csv-import') !== false) {
            // Reset-Notice
            if (get_transient('csv_import_stuck_reset_notice')) {
                echo '<div class="notice notice-warning is-dismissible"><p><strong>CSV Import:</strong> Ein hängender Import-Prozess wurde automatisch zurückgesetzt.</p></div>';
                delete_transient('csv_import_stuck_reset_notice');
            }
            
            // Aktivierungs-Notice
            if (get_transient('csv_import_activated_notice')) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>CSV Import Pro</strong> wurde erfolgreich aktiviert!</p></div>';
                delete_transient('csv_import_activated_notice');
            }
            
            // SEO-Plugin-Kompatibilitäts-Notice
            if (class_exists('CSV_Import_SEO_Preview')) {
                $compatibility = $this->check_seo_plugin_compatibility();
                if (empty($compatibility['active_plugin'])) {
                    echo '<div class="notice notice-info is-dismissible"><p><strong>CSV Import Pro:</strong> Für erweiterte SEO-Funktionen installieren Sie ein SEO-Plugin wie Yoast SEO oder RankMath.</p></div>';
                }
            }
            
            // SEO-Feldmapping-Notice
            if (isset($_GET['page']) && $_GET['page'] === 'csv-import-seo-preview') {
                $seo_mapping = get_option('csv_import_seo_field_mapping', []);
                if (empty($seo_mapping)) {
                    echo '<div class="notice notice-warning is-dismissible"><p><strong>SEO-Vorschau:</strong> Konfigurieren Sie das SEO-Feldmapping für optimale Ergebnisse.</p></div>';
                }
            }
            
            // Dependency-Check Notice
            if (!function_exists('csv_import_get_config')) {
                echo '<div class="notice notice-error"><p><strong>CSV Import Pro:</strong> Core-Funktionen nicht verfügbar. Plugin deaktivieren und wieder aktivieren.</p></div>';
            }
            
            // WordPress Cron Notice
            if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
                echo '<div class="notice notice-warning"><p><strong>CSV Import Pro:</strong> WordPress Cron ist deaktiviert. Geplante Imports benötigen einen externen Cron-Job.</p></div>';
            }
            
            // SEO-Einstellungen gespeichert Notice
            if (isset($_GET['seo_settings_saved']) && $_GET['seo_settings_saved'] === 'true') {
                echo '<div class="notice notice-success is-dismissible"><p><strong>SEO-Einstellungen</strong> wurden erfolgreich gespeichert!</p></div>';
            }
        }
    }

    /**
     * Hilfsfunktion: SEO-Feldmapping für Template verfügbar machen
     * @since 8.3
     */
    public function get_seo_field_mapping() {
        return get_option('csv_import_seo_field_mapping', [
            'title' => '',
            'description' => '',
            'keywords' => '',
            'canonical_url' => '',
            'og_title' => '',
            'og_description' => ''
        ]);
    }

    /**
     * Hilfsfunktion: CSV-Spalten für SEO-Mapping ermitteln
     * @since 8.3
     */
    public function get_available_csv_columns() {
        // Versuche CSV-Spalten aus der letzten Konfiguration zu holen
        if (function_exists('csv_import_get_last_parsed_data')) {
            $last_data = csv_import_get_last_parsed_data();
            if (!empty($last_data['headers'])) {
                return $last_data['headers'];
            }
        }

        // Fallback: Allgemeine Spalten-Beispiele
        return [
            'title' => 'Titel',
            'content' => 'Inhalt', 
            'excerpt' => 'Auszug',
            'meta_title' => 'SEO Titel',
            'meta_description' => 'SEO Beschreibung',
            'keywords' => 'Keywords',
            'category' => 'Kategorie',
            'tags' => 'Tags',
            'author' => 'Autor',
            'date' => 'Datum'
        ];
    }

    /**
     * AJAX-Handler für SEO-Vorschau-Updates
     * @since 8.3
     */
    public function ajax_update_seo_preview() {
        check_ajax_referer('csv_import_ajax', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_die('Keine Berechtigung');
        }
        
        $sample_data = isset($_POST['sample_data']) ? $_POST['sample_data'] : [];
        $field_mapping = isset($_POST['field_mapping']) ? $_POST['field_mapping'] : [];
        
        if (empty($sample_data) || !is_array($sample_data)) {
            wp_send_json_error('Keine Beispieldaten verfügbar');
        }
        
        ob_start();
        
        if (class_exists('CSV_Import_SEO_Preview') && method_exists('CSV_Import_SEO_Preview', 'render_preview_widget')) {
            CSV_Import_SEO_Preview::render_preview_widget($sample_data, $field_mapping);
        } else {
            $this->render_simple_seo_preview($sample_data);
        }
        
        $preview_html = ob_get_clean();
        
        wp_send_json_success([
            'preview_html' => $preview_html,
            'field_mapping' => $field_mapping
        ]);
    }
}
