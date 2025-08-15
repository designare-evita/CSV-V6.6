jQuery(document).ready(function($) {
    'use strict';
    
    console.log('🚀 SEO Preview Script gestartet');
    
    const SEOPreview = {
        currentDevice: 'desktop',
        isInitialized: false,
        
        init: function() {
            // Warten bis DOM vollständig geladen ist
            $(document).ready(() => {
                this.checkHTMLStructure();
                this.bindEvents();
                this.updatePreview();
                this.isInitialized = true;
                console.log('✅ SEO Preview initialisiert');
            });
        },
        
        checkHTMLStructure: function() {
            const requiredElements = [
                '.device-tab',
                '.seo-preview-container',
                '.seo-tab'
            ];
            
            requiredElements.forEach(selector => {
                const $element = $(selector);
                if ($element.length === 0) {
                    console.error(`❌ Element fehlt: ${selector}`);
                } else {
                    console.log(`✅ Element gefunden: ${selector} (${$element.length})`);
                }
            });
        },
        
        bindEvents: function() {
            // Alle bestehenden Events entfernen um Duplikate zu vermeiden
            $(document).off('click.seopreview');
            
            // Suchmaschinen-Tabs
            $(document).on('click.seopreview', '.seo-tab', (e) => {
                e.preventDefault();
                console.log('🔍 Search engine tab clicked');
                
                const $tab = $(e.currentTarget);
                $('.seo-tab').removeClass('active');
                $tab.addClass('active');
                
                const engine = $tab.data('engine');
                $('.serp-preview').removeClass('active');
                $(`.${engine}-serp`).addClass('active');
            });
            
            // Device-Tabs - VOLLSTÄNDIG NEU
            $(document).on('click.seopreview', '.device-tab', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const $tab = $(e.currentTarget);
                const device = $tab.data('device');
                
                console.log(`📱 Device tab clicked: ${device}`);
                console.log('Tab element:', $tab[0]);
                
                // Sofortiges visuelles Feedback
                $tab.css('background', '#ff6b6b');
                setTimeout(() => $tab.css('background', ''), 300);
                
                // Active State setzen
                $('.device-tab').removeClass('active');
                $tab.addClass('active');
                
                // Device State speichern
                this.currentDevice = device;
                
                // Container-Klasse umschalten
                const $container = $('.seo-preview-container');
                console.log('Container element:', $container[0]);
                
                if (device === 'mobile') {
                    console.log('📱 Wechsel zu Mobile View');
                    $container.addClass('mobile-view');
                    $container.css('border', '3px solid #ff6b6b'); // Debug-Border
                } else {
                    console.log('🖥️ Wechsel zu Desktop View');
                    $container.removeClass('mobile-view');
                    $container.css('border', '1px solid #e1e8ed'); // Standard-Border
                }
                
                // Font-Größen anpassen
                this.updateFontSizes(device);
                
                // Preview aktualisieren
                this.updatePreview();
            });
            
            // Input Events
            $(document).on('input.seopreview change.seopreview', '#seo_title, #seo_description', 
                this.debounce(() => this.updatePreview(), 300)
            );
            
            console.log('✅ Event listeners registriert');
        },
        
        updateFontSizes: function(device) {
            if (device === 'mobile') {
                $('.google-serp .serp-title').css('font-size', '18px');
                $('.google-serp .serp-description').css('font-size', '13px');
                $('.google-serp .serp-url').css('font-size', '12px');
                $('.bing-serp .serp-title').css('font-size', '16px');
                $('.bing-serp .serp-description').css('font-size', '12px');
                $('.bing-serp .serp-url').css('font-size', '11px');
            } else {
                $('.google-serp .serp-title').css('font-size', '20px');
                $('.google-serp .serp-description').css('font-size', '14px');
                $('.google-serp .serp-url').css('font-size', '14px');
                $('.bing-serp .serp-title').css('font-size', '18px');
                $('.bing-serp .serp-description').css('font-size', '13px');
                $('.bing-serp .serp-url').css('font-size', '13px');
            }
        },
        
        updatePreview: function(data) {
            const title = (data && data.seo_title) ? data.seo_title : this.getCurrentTitle();
            const description = (data && data.seo_description) ? data.seo_description : this.getCurrentDescription();
            const slug = this.generateSlug(title);
            
            this.updateSERPDisplay(title, description, slug);
            
            if (typeof csvSeoPreview !== 'undefined') {
                this.validateSEO(title, description, slug);
            }
        },
        
        getCurrentTitle: function() {
            const titleValue = $('#seo_title').val() || 'Beispiel Seitentitel';
            return titleValue.trim();
        },
        
        getCurrentDescription: function() {
            const descValue = $('#seo_description').val() || 'Beispiel Meta-Description für bessere Suchergebnisse.';
            return descValue.trim();
        },
        
        generateSlug: function(title) {
            if (!title) return 'beispiel-seite';
            return title.toLowerCase()
                       .replace(/[^a-z0-9\s-]/g, '')
                       .replace(/\s+/g, '-')
                       .replace(/-+/g, '-')
                       .replace(/^-|-$/g, '');
        },
        
        updateSERPDisplay: function(title, description, slug) {
            const domain = 'example.com';
            const displayUrl = `${domain}/${slug}`;
            
            let displayTitle = title;
            let displayDesc = description;
            
            // Mobile-spezifische Kürzung
            if (this.currentDevice === 'mobile') {
                displayTitle = title.length > 55 ? title.substring(0, 55) + '...' : title;
                displayDesc = description.length > 130 ? description.substring(0, 130) + '...' : description;
            }
            
            // Google Preview
            $('#google-title-preview').text(displayTitle);
            $('#google-desc-preview').text(displayDesc);
            $('#google-url-preview').text(displayUrl);
            
            // Bing Preview
            $('#bing-title-preview').text(displayTitle);
            $('#bing-desc-preview').text(displayDesc);
            $('#bing-url-preview').text(displayUrl);
            
            console.log(`📝 SERP aktualisiert (${this.currentDevice}): ${displayTitle.length}/${displayDesc.length} Zeichen`);
        },
        
        validateSEO: function(title, description, slug) {
            // Einfache Client-seitige Validierung falls AJAX nicht verfügbar
            const titleLimit = this.currentDevice === 'mobile' ? 55 : 60;
            const descLimit = this.currentDevice === 'mobile' ? 130 : 160;
            
            const titleLength = title.length;
            const descLength = description.length;
            
            // Metriken aktualisieren
            const titleStatus = titleLength <= titleLimit ? 'good' : (titleLength <= titleLimit + 10 ? 'warning' : 'bad');
            const descStatus = descLength <= descLimit ? 'good' : (descLength <= descLimit + 20 ? 'warning' : 'bad');
            
            $('#title-length-metric')
                .removeClass('good warning bad')
                .addClass(titleStatus)
                .text(`${titleLength}/${titleLimit} Zeichen`);
            
            $('#desc-length-metric')
                .removeClass('good warning bad')
                .addClass(descStatus)
                .text(`${descLength}/${descLimit} Zeichen`);
            
            // Score berechnen
            let score = 50;
            if (titleLength >= 30 && titleLength <= titleLimit) score += 25;
            if (descLength >= 120 && descLength <= descLimit) score += 25;
            
            $('#seo-score-metric')
                .removeClass('good warning bad')
                .addClass(score >= 70 ? 'good' : score >= 50 ? 'warning' : 'bad')
                .text(`${score}% (${this.currentDevice})`);
        },
        
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
    };
    
    // Debug-Buttons hinzufügen
    window.debugSEO = function() {
        console.log('=== SEO Preview Debug ===');
        console.log('Initialized:', SEOPreview.isInitialized);
        console.log('Current Device:', SEOPreview.currentDevice);
        console.log('Device Tabs:', $('.device-tab').length);
        console.log('Preview Container:', $('.seo-preview-container').length);
        console.log('Has mobile-view class:', $('.seo-preview-container').hasClass('mobile-view'));
        
        // Force Mobile Test
        $('.device-tab[data-device="mobile"]').trigger('click');
    };
    
    // Initialisierung
    SEOPreview.init();
    window.CSVSEOPreview = SEOPreview;
    
    console.log('✅ SEO Preview Script vollständig geladen');
    
    // Test nach 2 Sekunden
    setTimeout(() => {
        console.log('🧪 Auto-Test nach 2 Sekunden...');
        if ($('.device-tab').length > 0) {
            console.log('📱 Teste Mobile Button...');
            $('.device-tab[data-device="mobile"]').trigger('click');
        }
    }, 2000);
});
