jQuery(document).ready(function($) {
    'use strict';
    
    const SEOPreview = {
        currentDevice: 'desktop', // Track current device state
        
        init: function() {
            this.bindEvents();
            this.updatePreview(); // Initial preview with placeholder data
            console.log('✅ SEO Preview mit Mobile Support initialisiert');
        },
        
        bindEvents: function() {
            // Tab-Wechsel für Suchmaschinen
            $(document).on('click', '.seo-tab', function(e) {
                e.preventDefault();
                $('.seo-tab').removeClass('active');
                $(this).addClass('active');
                
                const engine = $(this).data('engine');
                $('.serp-preview').removeClass('active');
                $('.' + engine + '-serp').addClass('active');
                console.log('Search engine changed to:', engine);
            });
            
            // ⭐ KORRIGIERT: Tab-Wechsel für Geräte - VOLLSTÄNDIG ÜBERARBEITET
            $(document).on('click', '.device-tab', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('🔥 Device tab clicked!', $(this).data('device'));
                
                // Remove active state from all device tabs
                $('.device-tab').removeClass('active');
                $(this).addClass('active');
                
                // Get device type
                const device = $(this).data('device');
                SEOPreview.currentDevice = device;
                
                // Visual feedback
                $(this).addClass('clicked');
                setTimeout(() => $(this).removeClass('clicked'), 200);
                
                const $container = $('.seo-preview-container');
                
                if (device === 'mobile') {
                    console.log('📱 Switching to mobile view');
                    $container.addClass('mobile-view');
                    
                    // Mobile-spezifische Anpassungen
                    $('.google-serp .serp-title').css('font-size', '18px');
                    $('.google-serp .serp-description').css('font-size', '13px');
                    $('.google-serp .serp-url').css('font-size', '12px');
                    
                    $('.bing-serp .serp-title').css('font-size', '16px');
                    $('.bing-serp .serp-description').css('font-size', '12px');
                    $('.bing-serp .serp-url').css('font-size', '11px');
                    
                } else {
                    console.log('🖥️ Switching to desktop view');
                    $container.removeClass('mobile-view');
                    
                    // Desktop-spezifische Anpassungen
                    $('.google-serp .serp-title').css('font-size', '20px');
                    $('.google-serp .serp-description').css('font-size', '14px');
                    $('.google-serp .serp-url').css('font-size', '14px');
                    
                    $('.bing-serp .serp-title').css('font-size', '18px');
                    $('.bing-serp .serp-description').css('font-size', '13px');
                    $('.bing-serp .serp-url').css('font-size', '13px');
                }
                
                // Preview mit neuem Device aktualisieren
                SEOPreview.updatePreview();
            });
            
            // Live-Update bei Eingabe
            $(document).on('input change', '#seo_title, #seo_description', 
                this.debounce(this.updatePreview, 300)
            );
            
            // Fallback für fehlende Elemente
            setTimeout(() => {
                if ($('.device-tab').length === 0) {
                    console.warn('⚠️ Device tabs not found - check HTML structure');
                }
                if ($('.seo-preview-container').length === 0) {
                    console.warn('⚠️ Preview container not found - check HTML structure');
                }
            }, 1000);
        },
        
        updatePreview: function(data) {
            const title = (data && data.seo_title) ? data.seo_title : SEOPreview.getCurrentTitle();
            const description = (data && data.seo_description) ? data.seo_description : SEOPreview.getCurrentDescription();
            const slug = SEOPreview.generateSlug(title);
            
            // UI aktualisieren
            SEOPreview.updateSERPDisplay(title, description, slug);
            
            // AJAX-Validierung (mit Error Handling)
            if (typeof csvSeoPreview !== 'undefined') {
                SEOPreview.validateSEO(title, description, slug);
            }
        },
        
        getCurrentTitle: function() {
            return ($('#seo_title').val() || 'Beispiel Seitentitel').trim();
        },
        
        getCurrentDescription: function() {
            return ($('#seo_description').val() || 'Beispiel Meta-Description für bessere Suchergebnisse.').trim();
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
            const domain = (typeof csvSeoPreview !== 'undefined' && csvSeoPreview.domain) ? csvSeoPreview.domain : 'example.com';
            const displayUrl = domain + '/' + slug;
            
            // Device-spezifische Titel-/Description-Kürzung
            let displayTitle = title;
            let displayDesc = description;
            
            if (SEOPreview.currentDevice === 'mobile') {
                displayTitle = title.length > 55 ? title.substring(0, 55) + '...' : title;
                displayDesc = description.length > 130 ? description.substring(0, 130) + '...' : description;
            }
            
            // Google Preview Update
            $('#google-title-preview').text(displayTitle);
            $('#google-desc-preview').text(displayDesc);
            $('#google-url-preview').text(displayUrl);
            
            // Bing Preview Update
            $('#bing-title-preview').text(displayTitle);
            $('#bing-desc-preview').text(displayDesc);
            $('#bing-url-preview').text(displayUrl);
        },
        
        validateSEO: function(title, description, slug) {
            $.ajax({
                url: csvSeoPreview.ajaxurl,
                type: 'POST',
                data: {
                    action: 'csv_seo_preview_validate',
                    nonce: csvSeoPreview.nonce,
                    title: title,
                    description: description,
                    slug: slug,
                    device: SEOPreview.currentDevice
                },
                success: function(response) {
                    if (response.success) {
                        SEOPreview.updateMetrics(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.warn('SEO Preview: Validation failed', error);
                }
            });
        },
        
        updateMetrics: function(data) {
            // Device-spezifische Limits
            const titleLimit = SEOPreview.currentDevice === 'mobile' ? 55 : 60;
            const descLimit = SEOPreview.currentDevice === 'mobile' ? 130 : 160;
            
            // Titel-Länge
            const titleLength = data.title.length;
            const titleStatus = titleLength <= titleLimit ? 'good' : (titleLength <= titleLimit + 10 ? 'warning' : 'bad');
            $('#title-length-metric')
                .removeClass('good warning bad')
                .addClass(titleStatus)
                .text(titleLength + '/' + titleLimit + ' Zeichen' + SEOPreview.getStatusIcon(titleStatus));
            
            // Description-Länge
            const descLength = data.description.length;
            const descStatus = descLength <= descLimit ? 'good' : (descLength <= descLimit + 20 ? 'warning' : 'bad');
            $('#desc-length-metric')
                .removeClass('good warning bad')
                .addClass(descStatus)
                .text(descLength + '/' + descLimit + ' Zeichen' + SEOPreview.getStatusIcon(descStatus));
            
            // SEO-Score mit Device-Bonus
            let score = data.seo_score || 50;
            if (SEOPreview.currentDevice === 'mobile' && titleLength <= 55 && descLength <= 130) {
                score += 5; // Mobile-Optimierung Bonus
            }
            
            let scoreStatus = 'bad';
            let scoreText = 'Optimierung erforderlich';
            
            if (score >= 80) {
                scoreStatus = 'good';
                scoreText = 'Ausgezeichnet 🌟';
            } else if (score >= 60) {
                scoreStatus = 'good';
                scoreText = 'Gut 👍';
            } else if (score >= 40) {
                scoreStatus = 'warning';
                scoreText = 'Verbesserbar ⚠️';
            }
            
            $('#seo-score-metric')
                .removeClass('good warning bad')
                .addClass(scoreStatus)
                .text(scoreText + ' (' + Math.min(100, score) + '%)');
            
            // Empfehlungen aktualisieren
            SEOPreview.updateRecommendations(data.recommendations);
        },
        
        updateRecommendations: function(recommendations) {
            const container = $('#seo-recommendations');
            container.empty();
            
            // Device-spezifische Empfehlungen hinzufügen
            recommendations = recommendations || [];
            
            if (SEOPreview.currentDevice === 'mobile') {
                const currentTitle = SEOPreview.getCurrentTitle();
                const currentDesc = SEOPreview.getCurrentDescription();
                
                if (currentTitle.length > 55) {
                    recommendations.unshift({
                        type: 'warning',
                        message: `📱 Titel zu lang für Mobile (${currentTitle.length}/55 Zeichen)`
                    });
                }
                
                if (currentDesc.length > 130) {
                    recommendations.unshift({
                        type: 'warning',
                        message: `📱 Description zu lang für Mobile (${currentDesc.length}/130 Zeichen)`
                    });
                }
            }
            
            if (recommendations.length === 0) {
                const deviceText = SEOPreview.currentDevice === 'mobile' ? 'Mobile' : 'Desktop';
                container.html(`<div class="seo-recommendation"><span class="recommendation-icon good">✅</span><span class="recommendation-text">Alle ${deviceText} SEO-Kriterien erfüllt!</span></div>`);
                return;
            }
            
            recommendations.forEach(function(rec) {
                const iconMap = {
                    'info': 'ℹ️',
                    'warning': '⚠️',
                    'error': '❌'
                };
                
                const html = '<div class="seo-recommendation">' +
                    '<span class="recommendation-icon ' + rec.type + '">' + iconMap[rec.type] + '</span>' +
                    '<span class="recommendation-text">' + rec.message + '</span>' +
                    '</div>';
                
                container.append(html);
            });
        },
        
        getStatusIcon: function(status) {
            const icons = {
                'good': ' ✓',
                'warning': ' ⚠️',
                'bad': ' ❌'
            };
            return icons[status] || '';
        },
        
        debounce: function(func, wait) {
            let timeout;
            return function() {
                const context = this;
                const args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        }
    };
    
    // Initialisierung
    SEOPreview.init();
    
    // Global verfügbar machen
    window.CSVSEOPreview = SEOPreview;
});
