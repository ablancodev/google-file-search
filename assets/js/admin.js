/**
 * Admin JavaScript for Gemini File Search
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Test API Connection
        $('#gfs-test-connection').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $status = $('#gfs-connection-status');

            $button.prop('disabled', true).text('Probando...');
            $status.removeClass('success error').text('');

            $.ajax({
                url: gfsAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'gfs_test_connection',
                    nonce: gfsAdmin.admin_nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text('✓ ' + response.data);
                    } else {
                        $status.addClass('error').text('✗ ' + response.data);
                    }
                },
                error: function() {
                    $status.addClass('error').text('✗ Error de conexión');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Probar Conexión');
                }
            });
        });

        // Create Corpus
        $('#gfs-create-corpus').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $status = $('#gfs-corpus-status');

            if (!confirm('¿Estás seguro de crear un nuevo corpus? Esto generará un nuevo ID.')) {
                return;
            }

            $button.prop('disabled', true).text('Creando...');
            $status.removeClass('success error').text('');

            $.ajax({
                url: gfsAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'gfs_create_corpus',
                    nonce: gfsAdmin.admin_nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#gfs_corpus_id').val(response.data.corpus_id);
                        $status.addClass('success').text('✓ Corpus creado exitosamente');
                    } else {
                        $status.addClass('error').text('✗ ' + response.data);
                    }
                },
                error: function() {
                    $status.addClass('error').text('✗ Error al crear corpus');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Crear Nuevo Corpus');
                }
            });
        });

        // Bulk Sync
        $('#gfs-bulk-sync').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $progress = $('#gfs-sync-progress');
            var $status = $('#gfs-sync-status');
            var $results = $('#gfs-sync-results');

            if (!confirm('¿Estás seguro de sincronizar todos los productos? Este proceso puede tardar varios minutos.')) {
                return;
            }

            $button.prop('disabled', true);
            $progress.show();
            $status.text('Sincronizando productos...');
            $results.empty();

            $.ajax({
                url: gfsAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'gfs_bulk_sync',
                    nonce: gfsAdmin.admin_nonce
                },
                timeout: 300000, // 5 minutes
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        $status.text('Sincronización completada');

                        var html = '<div class="gfs-sync-summary">';
                        html += '<h3>Resultados de Sincronización</h3>';
                        html += '<div class="gfs-sync-stat success">✓ Exitosos: ' + data.success + '</div>';
                        html += '<div class="gfs-sync-stat error">✗ Fallidos: ' + data.failed + '</div>';

                        if (data.errors && data.errors.length > 0) {
                            html += '<h4>Errores:</h4><ul>';
                            data.errors.forEach(function(error) {
                                html += '<li>Producto #' + error.product_id + ': ' + error.error + '</li>';
                            });
                            html += '</ul>';
                        }

                        html += '</div>';
                        $results.html(html);

                        // Reload page after 2 seconds
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $status.text('Error en sincronización');
                        $results.html('<div class="gfs-error">' + response.data + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $status.text('Error en sincronización');
                    $results.html('<div class="gfs-error">Error: ' + error + '</div>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });

        // Test Search
        $('#gfs-test-search').on('click', function(e) {
            e.preventDefault();
            var query = $('#gfs-test-query').val();
            var $results = $('#gfs-test-results');

            if (!query) {
                alert('Por favor ingresa una consulta de búsqueda');
                return;
            }

            $results.html('<div class="gfs-loading"><span class="gfs-spinner"></span> Buscando...</div>');

            $.ajax({
                url: gfsAdmin.rest_url + 'search',
                type: 'GET',
                data: {
                    query: query,
                    limit: 10
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', gfsAdmin.nonce);
                },
                success: function(response) {
                    if (response.success && response.products.length > 0) {
                        var html = '<h3>Se encontraron ' + response.total_results + ' resultados</h3>';
                        response.products.forEach(function(product) {
                            html += renderProductCard(product);
                        });
                        $results.html(html);
                    } else {
                        $results.html('<div class="gfs-no-results">No se encontraron productos</div>');
                    }
                },
                error: function(xhr) {
                    var errorMsg = 'Error en la búsqueda';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    $results.html('<div class="gfs-error">' + errorMsg + '</div>');
                }
            });
        });

        // Test AI Search
        $('#gfs-ai-search').on('click', function(e) {
            e.preventDefault();
            var query = $('#gfs-ai-query').val();
            var $results = $('#gfs-ai-results');

            if (!query) {
                alert('Por favor ingresa una consulta');
                return;
            }

            $results.html('<div class="gfs-loading"><span class="gfs-spinner"></span> El IA está procesando...</div>');

            $.ajax({
                url: gfsAdmin.rest_url + 'search-ai',
                type: 'POST',
                data: JSON.stringify({ query: query }),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', gfsAdmin.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="gfs-ai-response-box">';
                        html += '<h3>Respuesta del IA</h3>';
                        html += '<p>' + response.ai_response + '</p>';
                        html += '</div>';

                        if (response.products && response.products.length > 0) {
                            html += '<h3>Productos Relacionados</h3>';
                            response.products.forEach(function(product) {
                                html += renderProductCard(product);
                            });
                        }

                        $results.html(html);
                    } else {
                        $results.html('<div class="gfs-no-results">No se encontraron resultados</div>');
                    }
                },
                error: function(xhr) {
                    var errorMsg = 'Error en la búsqueda';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    $results.html('<div class="gfs-error">' + errorMsg + '</div>');
                }
            });
        });

        // Manage Stores Page
        if ($('#gfs-stores-container').length) {
            loadStores();
        }

        $('#gfs-refresh-stores').on('click', function(e) {
            e.preventDefault();
            loadStores();
        });

        $('#gfs-clean-orphans').on('click', function(e) {
            e.preventDefault();
            if (!confirm('¿Eliminar documentos huérfanos de productos que ya no existen?')) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text('Limpiando...');

            $.ajax({
                url: gfsAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'gfs_clean_orphans',
                    nonce: gfsAdmin.admin_nonce
                },
                success: function(response) {
                    if (response.success) {
                        var msg = 'Registros limpiados: ' + response.data.cleaned + ' de ' + response.data.total;
                        msg += '\n\nDocumentos en Gemini: ' + response.data.gemini_docs;
                        alert(msg);
                        loadStores();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error al limpiar documentos huérfanos');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Limpiar Documentos Huérfanos');
                }
            });
        });

        function loadStores() {
            var $container = $('#gfs-stores-container');
            $container.html('<p>Cargando stores...</p>');

            $.ajax({
                url: gfsAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'gfs_list_stores',
                    nonce: gfsAdmin.admin_nonce
                },
                success: function(response) {
                    if (response.success) {
                        renderStores(response.data);
                    } else {
                        $container.html('<div class="error"><p>Error: ' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    $container.html('<div class="error"><p>Error al cargar stores</p></div>');
                }
            });
        }

        function renderStores(data) {
            var $container = $('#gfs-stores-container');
            var html = '';

            if (!data.stores || data.stores.length === 0) {
                html = '<p>No hay stores creados.</p>';
            } else {
                html += '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr>';
                html += '<th>Store ID</th>';
                html += '<th>Nombre</th>';
                html += '<th>Productos (local)</th>';
                html += '<th>Fecha Creación</th>';
                html += '<th>Acciones</th>';
                html += '</tr></thead>';
                html += '<tbody>';

                data.stores.forEach(function(store) {
                    var isActive = store.name === data.current_store;
                    var localCount = data.local_stats[store.name] || 0;

                    html += '<tr' + (isActive ? ' style="background:#e7f7e7;"' : '') + '>';
                    html += '<td><code>' + store.name + '</code>' + (isActive ? ' <strong>(ACTIVO)</strong>' : '') + '</td>';
                    html += '<td>' + (store.displayName || 'N/A') + '</td>';
                    html += '<td>' + localCount + '</td>';
                    html += '<td>' + (store.createTime || 'N/A') + '</td>';
                    html += '<td>';
                    html += '<button class="button button-small gfs-view-docs" data-store="' + store.name + '">Ver Documentos</button> ';
                    if (!isActive) {
                        html += '<button class="button button-small button-link-delete gfs-delete-store" data-store="' + store.name + '">Eliminar</button>';
                    }
                    html += '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
            }

            $container.html(html);
        }

        $(document).on('click', '.gfs-delete-store', function() {
            var $button = $(this);
            var storeId = $button.data('store');

            if (!confirm('¿Estás seguro de eliminar este store? Todos los documentos se perderán.')) {
                return;
            }

            $button.prop('disabled', true).text('Eliminando...');

            $.ajax({
                url: gfsAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'gfs_delete_store',
                    nonce: gfsAdmin.admin_nonce,
                    store_id: storeId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Store eliminado exitosamente');
                        loadStores();
                    } else {
                        alert('Error: ' + response.data);
                        $button.prop('disabled', false).text('Eliminar');
                    }
                },
                error: function() {
                    alert('Error al eliminar store');
                    $button.prop('disabled', false).text('Eliminar');
                }
            });
        });

        $(document).on('click', '.gfs-view-docs', function() {
            var $button = $(this);
            var storeId = $button.data('store');

            $button.prop('disabled', true).text('Cargando...');

            $.ajax({
                url: gfsAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'gfs_list_documents',
                    nonce: gfsAdmin.admin_nonce,
                    store_id: storeId
                },
                success: function(response) {
                    if (response.success) {
                        showDocumentsModal(storeId, response.data);
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error al cargar documentos');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Ver Documentos');
                }
            });
        });

        function showDocumentsModal(storeId, documents) {
            var html = '<div class="gfs-modal-overlay" id="gfs-docs-modal">';
            html += '<div class="gfs-modal-content">';
            html += '<span class="gfs-modal-close">&times;</span>';
            html += '<h2>Documentos en Store</h2>';
            html += '<p><code>' + storeId + '</code></p>';

            if (documents.length === 0) {
                html += '<p>No hay documentos registrados en este store.</p>';
            } else {
                html += '<table class="wp-list-table widefat">';
                html += '<thead><tr><th>Producto</th><th>Document ID</th><th>Fecha</th><th>Estado</th><th>Acción</th></tr></thead>';
                html += '<tbody>';

                documents.forEach(function(doc) {
                    html += '<tr' + (!doc.product_exists ? ' style="background:#ffe;"' : '') + '>';
                    html += '<td>#' + doc.product_id + ' - ' + doc.product_name + '</td>';
                    html += '<td><code>' + doc.document_id.substring(doc.document_id.length - 30) + '</code></td>';
                    html += '<td>' + doc.sync_date + '</td>';
                    html += '<td>' + (!doc.product_exists ? '<span style="color:#d63638;">Producto eliminado</span>' : '<span style="color:#008a20;">Activo</span>') + '</td>';
                    html += '<td><button class="button button-small button-link-delete gfs-delete-doc" data-doc="' + doc.document_id + '" data-product="' + doc.product_id + '">Eliminar</button></td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
            }

            html += '</div></div>';

            $('body').append(html);
        }

        $(document).on('click', '.gfs-delete-doc', function() {
            var $button = $(this);
            var docId = $button.data('doc');
            var productId = $button.data('product');

            if (!confirm('¿Eliminar este documento?')) {
                return;
            }

            $button.prop('disabled', true).text('Eliminando...');

            $.ajax({
                url: gfsAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'gfs_delete_document',
                    nonce: gfsAdmin.admin_nonce,
                    document_id: docId,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Error: ' + response.data);
                        $button.prop('disabled', false).text('Eliminar');
                    }
                },
                error: function() {
                    alert('Error al eliminar documento');
                    $button.prop('disabled', false).text('Eliminar');
                }
            });
        });

        $(document).on('click', '.gfs-modal-close, .gfs-modal-overlay', function(e) {
            if (e.target === this) {
                $('#gfs-docs-modal').remove();
            }
        });

        // Helper function to render product card
        function renderProductCard(product) {
            var html = '<div class="gfs-test-product">';

            if (product.image) {
                html += '<img src="' + product.image + '" alt="' + product.name + '">';
            }

            html += '<div class="gfs-test-product-info">';
            html += '<div class="gfs-test-product-title">' + product.name;

            if (product.relevance_score) {
                html += '<span class="gfs-relevance-score">Relevancia: ' +
                        (product.relevance_score * 100).toFixed(1) + '%</span>';
            }

            html += '</div>';
            html += '<div class="gfs-test-product-price">' + product.price_html + '</div>';

            if (product.description) {
                html += '<div class="gfs-test-product-description">' + product.description + '</div>';
            }

            html += '<div class="gfs-test-product-meta">';
            html += 'SKU: ' + (product.sku || 'N/A') + ' | ';
            html += '<a href="' + product.permalink + '" target="_blank">Ver producto →</a>';
            html += '</div>';
            html += '</div></div>';

            return html;
        }
    });

})(jQuery);
