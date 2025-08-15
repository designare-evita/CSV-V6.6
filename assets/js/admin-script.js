/**
 * CSV Import Pro Admin JavaScript - Finale korrigierte Version 9.0
 * Löst jQuery-Konflikte und stellt die Funktionalität sicher.
 */

// Der gesamte Code wird in diese Funktion eingeschlossen, um das '$' Alias sicher zu verwenden.
(function($) {
    'use strict';

    // ===================================================================
    // HAUPTOBJEKT FÜR DAS PLUGIN
    // ===================================================================
    const CSVImportAdmin = {

        version: '9.0-final',
        
        elements: {},
        status: {
            importRunning: false,
            validationInProgress: false,
            progressInterval: null
        },
        
        /**
         * Initialisiert das gesamte Skript, wenn das Dokument bereit ist.
         */
        init: function() {
            // Prüft, ob die PHP-Daten für AJAX verfügbar sind.
            if (typeof csvImportAjax === 'undefined') {
                console.error('❌ CSV Import: AJAX-Konfiguration (csvImportAjax) fehlt. Das Skript kann nicht ausgeführt werden.');
                return;
            }
            // Zwischenspeichern der DOM-Elemente für schnelleren Zugriff.
            this.cacheElements();
            // Binden der Event-Listener (z.B. Klicks auf Buttons).
            this.bindEvents();
            // Setzen des initialen Status (z.B. ob bereits ein Import läuft).
            this.initializeStatus();
            console.log(`🔧 CSV Import Admin v${this.version} initialisiert.`);
        },
        
        /**
         * Speichert häufig verwendete jQuery-Selektoren.
         */
        cacheElements: function() {
            this.elements = {
                resultsContainer: $('#csv-test-results'),
                sampleDataContainer: $('#csv-sample-data-container'),
                importButtons: $('.csv-import-btn'),
                progressBar: $('.progress-bar-fill'),
                mappingContainer: $('#csv-column-mapping-container')
            };
        },

        /**
         * Weist den HTML-Elementen Funktionen zu.
         */
        bindEvents: function() {
            const self = this;
            
            // Event-Handler für die "Import starten"-Buttons.
            this.elements.importButtons.on('click', function(e) {
                e.preventDefault();
                self.handleImportClick($(this));
            });

            // Globale Funktionen für die `onclick`-Attribute im HTML verfügbar machen.
            window.csvImportTestConfig = () => self.testConfiguration();
            window.csvImportValidateCSV = (type) => self.validateCSV(type);
        },

        /**
         * Liest den initialen Status aus den von PHP übergebenen Daten.
         */
        initializeStatus: function() {
            this.status.importRunning = csvImportAjax.import_running || false;
            this.updateUIState();
            if (this.status.importRunning) {
                this.startProgressUpdates();
            }
        },

        /**
         * Eine zentrale Funktion für alle AJAX-Anfragen an den Server.
         */
        performAjaxRequest: function(data) {
            return $.ajax({
                url: csvImportAjax.ajaxurl,
                type: 'POST',
                data: $.extend({ nonce: csvImportAjax.nonce }, data),
            }).fail(() => {
                console.error("❌ CSV Import: AJAX-Anfrage fehlgeschlagen.", data.action);
            });
        },

        /**
         * Startet den Test der Plugin-Konfiguration.
         */
        testConfiguration: function() {
            if (this.status.validationInProgress) return;
            this.status.validationInProgress = true;
            this.showTestProgress('Konfiguration wird geprüft...');
            this.performAjaxRequest({ action: 'csv_import_validate', type: 'config' })
                .done(response => this.handleValidationResult(response, 'config'))
                .always(() => { this.status.validationInProgress = false; });
        },

        /**
         * Startet die Validierung einer CSV-Datei (lokal oder Dropbox).
         */
        validateCSV: function(type) {
            if (this.status.validationInProgress) return;
            this.status.validationInProgress = true;
            const typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
            this.showTestProgress(`${typeLabel} CSV wird validiert...`);
            this.performAjaxRequest({ action: 'csv_import_validate', type: type })
                .done(response => this.handleValidationResult(response, type))
                .always(() => { this.status.validationInProgress = false; });
        },

        /**
         * Verarbeitet das Ergebnis der Validierung und zeigt es an.
         */
        handleValidationResult: function(response, type) {
            const data = response.data || {};
            this.showTestResult(data.message || 'Ein unbekannter Fehler ist aufgetreten.', response.success);

            if (response.success && data.columns && data.sample_data) {
                this.showSampleData(data.columns, data.sample_data);
                this.showColumnMappingUI(data.columns);
                
                // SEO Vorschau mit den neuen Daten aktualisieren
                if (window.CSVSEOPreview && data.sample_data[0]) {
                    const sample_row = data.sample_data[0];
                    const columns = data.columns;
                    const preview_data = {
                        seo_title: this.getDataFromRow(sample_row, columns, ['post_title', 'title']),
                        seo_description: this.getDataFromRow(sample_row, columns, ['post_excerpt', 'excerpt', 'description'])
                    };
                    window.CSVSEOPreview.updatePreview(preview_data);
                }
            } else {
                this.clearSampleData();
                this.elements.mappingContainer.hide().empty();
            }
        },

        /**
         * Sucht in einer CSV-Zeile nach möglichen Spaltennamen.
         */
        getDataFromRow: function(row, columns, possibleKeys) {
            for (const key of possibleKeys) {
                const index = columns.indexOf(key);
                if (index !== -1 && row[index]) {
                    return row[index];
                }
            }
            return '';
        },
        
        /**
         * Behandelt den Klick auf einen Import-Button.
         */
        handleImportClick: function($button) {
            const source = $button.data('source');
            const sourceName = source.charAt(0).toUpperCase() + source.slice(1);
            if (!confirm(`Den Import von der Quelle "${sourceName}" wirklich starten?`)) return;
            this.startImport(source);
        },

        /**
         * Startet den eigentlichen Importprozess.
         */
        startImport: function(source) {
            this.status.importRunning = true;
            this.updateUIState();
            const mappingData = {};
            this.elements.mappingContainer.find('select').each(function() {
                const columnName = $(this).attr('name').replace(/csv_mapping\[|\]/g, '');
                if($(this).val()) {
                    mappingData[columnName] = $(this).val();
                }
            });

            this.performAjaxRequest({ action: 'csv_import_start', source: source, mapping: mappingData })
                .done(response => {
                    if (response.success) {
                        alert(response.data.message || 'Import abgeschlossen.');
                        window.location.reload();
                    } else {
                        alert('Import fehlgeschlagen: ' + (response.data.message || 'Unbekannter Fehler.'));
                    }
                })
                .fail(() => alert('Ein schwerwiegender Serverfehler ist beim Import aufgetreten.'))
                .always(() => {
                    this.status.importRunning = false;
                    this.updateUIState();
                });
            this.startProgressUpdates();
        },

        /**
         * Aktualisiert die Benutzeroberfläche (z.B. Deaktivieren der Buttons).
         */
        updateUIState: function() {
            this.elements.importButtons.prop('disabled', this.status.importRunning);
        },
        
        /**
         * Startet die regelmäßige Abfrage des Import-Fortschritts.
         */
        startProgressUpdates: function() {
            if (this.status.progressInterval) clearInterval(this.status.progressInterval);
            this.status.progressInterval = setInterval(() => this.updateProgress(), 5000);
        },
        
        /**
         * Fragt den Fortschritt vom Server ab und aktualisiert die Fortschrittsanzeige.
         */
        updateProgress: function() {
            this.performAjaxRequest({ action: 'csv_import_get_progress' })
                .done(response => {
                    if (response.success && response.data) {
                        const progress = response.data;
                        this.elements.progressBar.css('width', progress.percent + '%');
                        if (!progress.running && this.status.progressInterval) {
                            clearInterval(this.status.progressInterval);
                        }
                    }
                });
        },

        // --- HILFSFUNKTIONEN FÜR DIE UI ---

        showTestProgress: function(message) {
            this.elements.resultsContainer.html(`<div class="test-result test-progress">🔄 ${message}</div>`);
        },

        showTestResult: function(message, success) {
            const resultClass = success ? 'test-success' : 'test-error';
            const icon = success ? '✅' : '❌';
            this.elements.resultsContainer.html(`<div class="test-result ${resultClass}">${icon} ${message}</div>`);
        },
        
        showSampleData: function(columns, sampleData) {
            let tableHtml = '<table class="wp-list-table widefat striped"><thead><tr>';
            columns.forEach(col => tableHtml += `<th>${col}</th>`);
            tableHtml += '</tr></thead><tbody>';
            sampleData.forEach(row => {
                tableHtml += '<tr>';
                row.forEach(cell => {
                     tableHtml += `<td>${cell || ''}</td>`;
                });
                tableHtml += '</tr>';
            });
            tableHtml += '</tbody></table>';
            this.elements.sampleDataContainer.html(tableHtml);
        },

        clearSampleData: function() {
            this.elements.sampleDataContainer.empty();
        },
        
        showColumnMappingUI: function(columns) {
            const targetFields = ['post_title', 'post_content', 'post_excerpt', 'post_name', 'featured_image'];
            let tableHtml = '<h4>Spalten zuordnen</h4><table class="wp-list-table widefat striped"><thead><tr><th>CSV-Spalte</th><th>WordPress-Feld</th></tr></thead><tbody>';
            columns.forEach(column => {
                let optionsHtml = '<option value="">-- Ignorieren --</option>';
                targetFields.forEach(field => {
                    const isSelected = column.toLowerCase().replace(/[ -]/g, '_') === field;
                    optionsHtml += `<option value="${field}" ${isSelected ? 'selected' : ''}>${field}</option>`;
                });
                tableHtml += `<tr><td><strong>${column}</strong></td><td><select name="csv_mapping[${column}]">${optionsHtml}</select></td></tr>`;
            });
            tableHtml += '</tbody></table>';
            this.elements.mappingContainer.html(tableHtml).show();
        },
    };

    // ===================================================================
    // INITIALISIERUNG, SOBALD DIE SEITE BEREIT IST
    // ===================================================================

    $(document).ready(function() {
        CSVImportAdmin.init();
    });

})(jQuery);
