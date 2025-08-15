/**
 * CSV Import Pro Admin JavaScript 
 * Löst alle Syntaxfehler und begrenzt die Spaltenanzeige.
 */

(function($) {
    'use strict';

    // Globale Funktionen für die `onclick`-Attribute im HTML verfügbar machen.
    window.csvImportTestConfig = () => { if (window.CSVImportAdmin) window.CSVImportAdmin.testConfiguration(); };
    window.csvImportValidateCSV = (type) => { if (window.CSVImportAdmin) window.CSVImportAdmin.validateCSV(type); };

    const CSVImportAdmin = {
        version: '9.1-final',
        elements: {},
        status: {
            importRunning: false,
            validationInProgress: false,
            progressInterval: null
        },

        init: function() {
            if (typeof csvImportAjax === 'undefined') {
                console.error('❌ CSV Import: AJAX-Konfiguration fehlt.');
                return;
            }
            this.cacheElements();
            this.bindEvents();
            this.initializeStatus();
            console.log(`🔧 CSV Import Admin v${this.version} initialisiert.`);
        },
        
        cacheElements: function() {
            this.elements = {
                resultsContainer: $('#csv-test-results'),
                sampleDataContainer: $('#csv-sample-data-container'),
                importButtons: $('.csv-import-btn'),
                progressBar: $('.progress-bar-fill, .csv-import-progress-fill'),
                mappingContainer: $('#csv-column-mapping-container')
            };
        },

        bindEvents: function() {
            const self = this;
            this.elements.importButtons.on('click', function(e) {
                e.preventDefault();
                self.handleImportClick($(this));
            });
        },

        initializeStatus: function() {
            this.status.importRunning = csvImportAjax.import_running || false;
            this.updateUIState();
            if (this.status.importRunning) {
                this.startProgressUpdates();
            }
        },

        performAjaxRequest: function(data) {
            return $.ajax({
                url: csvImportAjax.ajaxurl,
                type: 'POST',
                data: $.extend({ nonce: csvImportAjax.nonce }, data),
            }).fail(() => {
                console.error("❌ CSV Import: AJAX-Anfrage fehlgeschlagen.", data.action);
            });
        },

        testConfiguration: function() {
            if (this.status.validationInProgress) return;
            this.status.validationInProgress = true;
            this.showTestProgress('Konfiguration wird geprüft...');
            this.performAjaxRequest({ action: 'csv_import_validate', type: 'config' })
                .done(response => this.handleValidationResult(response, 'config'))
                .always(() => { this.status.validationInProgress = false; });
        },

        validateCSV: function(type) {
            if (this.status.validationInProgress) return;
            this.status.validationInProgress = true;
            const typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
            this.showTestProgress(`${typeLabel} CSV wird validiert...`);
            this.performAjaxRequest({ action: 'csv_import_validate', type: type })
                .done(response => this.handleValidationResult(response, type))
                .always(() => { this.status.validationInProgress = false; });
        },

        handleValidationResult: function(response, type) {
            const data = response.data || {};
            this.showTestResult(data.message || 'Ein unbekannter Fehler ist aufgetreten.', response.success);

            if (response.success && data.columns && data.sample_data) {
                this.showSampleData(data.columns, data.sample_data);
                this.showColumnMappingUI(data.columns);
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

        getDataFromRow: function(row, columns, possibleKeys) {
            for (const key of possibleKeys) {
                const index = columns.indexOf(key);
                if (index !== -1 && row[index]) {
                    return row[index];
                }
            }
            return '';
        },
        
        handleImportClick: function($button) {
            const source = $button.data('source');
            const sourceName = source.charAt(0).toUpperCase() + source.slice(1);
            if (!confirm(`Den Import von der Quelle "${sourceName}" wirklich starten?`)) return;
            this.startImport(source);
        },

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
    if (response.success && response.data) {
        // ALT: alert(response.data.message || 'Import abgeschlossen.');
        // NEU: Zeige die formatierte Erfolgsmeldung an
        $('#success-count').text(response.data.processed || 0);
        $('#success-source').text(source.charAt(0).toUpperCase() + source.slice(1));
        $('#csv-import-success-message').slideDown();

        // Optional: Nach kurzer Zeit automatisch zum oberen Rand der Seite scrollen
        $('html, body').animate({ scrollTop: 0 }, 'slow');

    } else {
        // Fehlerfall bleibt gleich
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

        updateUIState: function() {
            this.elements.importButtons.prop('disabled', this.status.importRunning);
        },
        
        startProgressUpdates: function() {
            if (this.status.progressInterval) clearInterval(this.status.progressInterval);
            this.status.progressInterval = setInterval(() => this.updateProgress(), 5000);
        },
        
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

        showTestProgress: function(message) {
            this.elements.resultsContainer.html(`<div class="test-result test-progress">🔄 ${message}</div>`);
        },

        showTestResult: function(message, success) {
            const resultClass = success ? 'test-success' : 'test-error';
            const icon = success ? '✅' : '❌';
            this.elements.resultsContainer.html(`<div class="test-result ${resultClass}">${icon} ${message}</div>`);
        },
        
        showSampleData: function(columns, sampleData) {
            const maxCols = 6; // Anzeige auf 6 Spalten begrenzen
            const displayColumns = columns.slice(0, maxCols);
            const hasMoreCols = columns.length > maxCols;

            let tableHtml = `<div class="sample-data-header"><h4>📊 Beispieldaten</h4><span class="sample-info">${sampleData.length} Zeilen, ${columns.length} Spalten</span></div><table class="wp-list-table widefat striped"><thead><tr>`;
            displayColumns.forEach(col => tableHtml += `<th>${col}</th>`);
            if (hasMoreCols) tableHtml += `<th class="more-cols">...</th>`;
            tableHtml += '</tr></thead><tbody>';
            sampleData.forEach(row => {
                tableHtml += '<tr>';
                const displayRow = row.slice(0, maxCols);
                displayRow.forEach(cell => {
                     tableHtml += `<td>${cell || ''}</td>`;
                });
                if (hasMoreCols) tableHtml += `<td class="more-cols">...</td>`;
                tableHtml += '</tr>';
            });
            tableHtml += '</tbody></table>';
            if (hasMoreCols) {
                tableHtml += `<p class="description">Zeige ${maxCols} von ${columns.length} Spalten zur Vorschau.</p>`;
            }
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
        }
    };

    $(document).ready(function() {
        if (typeof CSVImportAdmin !== 'undefined') {
            CSVImportAdmin.init();
            window.CSVImportAdmin = CSVImportAdmin;
        }
    });

})(jQuery);
