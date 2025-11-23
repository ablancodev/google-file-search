/**
 * Frontend JavaScript for Gemini File Search
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Basic Search Form
        $('.gfs-search-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $wrapper = $form.closest('.gfs-search-wrapper');
            var query = $form.find('.gfs-search-input').val();
            var limit = $form.data('results-limit') || 10;
            var $loading = $form.find('.gfs-search-loading');
            var $results = $wrapper.find('.gfs-search-results');
            var $button = $form.find('.gfs-search-button');

            if (!query) {
                return;
            }

            $button.prop('disabled', true);
            $loading.show();
            $results.empty();

            $.ajax({
                url: gfsData.rest_url + 'search',
                type: 'GET',
                data: {
                    query: query,
                    limit: limit
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', gfsData.nonce);
                },
                success: function(response) {
                    if (response.success && response.products.length > 0) {
                        renderProducts(response.products, $results, response.total_results);
                    } else {
                        showNoResults($results);
                    }
                },
                error: function(xhr) {
                    showError($results, xhr);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $loading.hide();
                }
            });
        });

        // AI Search Form
        $('.gfs-ai-search-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $wrapper = $form.closest('.gfs-ai-search-wrapper');
            var query = $form.find('.gfs-ai-search-input').val();
            var $loading = $form.find('.gfs-ai-search-loading');
            var $response = $wrapper.find('.gfs-ai-response');
            var $results = $wrapper.find('.gfs-ai-search-results');
            var $button = $form.find('.gfs-ai-search-button');

            if (!query) {
                return;
            }

            $button.prop('disabled', true);
            $loading.show();
            $response.empty();
            $results.empty();

            $.ajax({
                url: gfsData.rest_url + 'search-ai',
                type: 'POST',
                data: JSON.stringify({ query: query }),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', gfsData.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        // Show AI response
                        if (response.ai_response) {
                            var aiHtml = '<h3>Recomendación del IA</h3>';
                            aiHtml += '<div class="gfs-ai-response-content">' +
                                     formatText(response.ai_response) + '</div>';
                            $response.html(aiHtml);
                        }

                        // Show products
                        if (response.products && response.products.length > 0) {
                            renderProducts(response.products, $results, response.products.length);
                        }
                    } else {
                        showNoResults($results);
                    }
                },
                error: function(xhr) {
                    showError($results, xhr);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $loading.hide();
                }
            });
        });

        // Render products in grid
        function renderProducts(products, $container, totalResults) {
            var html = '<div class="gfs-results-header">';
            html += 'Se encontraron <strong>' + totalResults + '</strong> resultados';
            html += '</div>';
            html += '<div class="gfs-product-grid">';

            products.forEach(function(product) {
                html += '<div class="gfs-product-card">';

                // Image
                if (product.image) {
                    html += '<a href="' + product.permalink + '">';
                    html += '<img src="' + product.image + '" alt="' + escapeHtml(product.name) + '" class="gfs-product-image">';
                    html += '</a>';
                } else {
                    html += '<div class="gfs-product-image-placeholder">Sin imagen</div>';
                }

                // Product info
                html += '<div class="gfs-product-info">';
                html += '<div class="gfs-product-title">';
                html += '<a href="' + product.permalink + '">' + escapeHtml(product.name) + '</a>';

                if (product.relevance_score) {
                    html += '<span class="gfs-relevance-badge">' +
                            Math.round(product.relevance_score * 100) + '%</span>';
                }

                html += '</div>';

                // Price
                html += '<div class="gfs-product-price">' + product.price_html + '</div>';

                // Description
                if (product.description) {
                    html += '<div class="gfs-product-description">' +
                            escapeHtml(product.description) + '</div>';
                }

                // Meta
                html += '<div class="gfs-product-meta">';

                // Stock status
                var stockClass = product.in_stock ? 'in-stock' : 'out-of-stock';
                var stockText = product.in_stock ? 'En stock' : 'Sin stock';
                html += '<span class="gfs-product-stock ' + stockClass + '">' + stockText + '</span>';

                // Categories
                if (product.categories && product.categories.length > 0) {
                    html += '<span class="gfs-product-categories"> | ' +
                            product.categories.join(', ') + '</span>';
                }

                html += '</div>'; // meta
                html += '</div>'; // info
                html += '</div>'; // card
            });

            html += '</div>'; // grid
            $container.html(html);
        }

        // Show no results message
        function showNoResults($container) {
            var html = '<div class="gfs-no-results">';
            html += '<div class="gfs-no-results-icon">🔍</div>';
            html += '<p>No se encontraron productos que coincidan con tu búsqueda.</p>';
            html += '<p>Intenta con otros términos o busca de manera más general.</p>';
            html += '</div>';
            $container.html(html);
        }

        // Show error message
        function showError($container, xhr) {
            var errorMsg = 'Ocurrió un error al realizar la búsqueda.';

            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }

            var html = '<div class="gfs-error">';
            html += '<strong>Error:</strong> ' + errorMsg;
            html += '</div>';
            $container.html(html);
        }

        // Format text (convert line breaks to paragraphs)
        function formatText(text) {
            return text.split('\n\n').map(function(p) {
                return '<p>' + escapeHtml(p).replace(/\n/g, '<br>') + '</p>';
            }).join('');
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    });

})(jQuery);
