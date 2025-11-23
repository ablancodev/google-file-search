# Google Gemini File Search for WooCommerce

Plugin de WordPress que integra la API de Google Gemini File Search con WooCommerce para proporcionar búsqueda semántica de productos.

## Descripción

Este plugin permite crear un buscador semántico inteligente para tu tienda WooCommerce utilizando la tecnología de búsqueda de archivos de Google Gemini. Los productos se sincronizan automáticamente con Gemini, lo que permite a los usuarios encontrar productos usando lenguaje natural y búsquedas semánticas avanzadas.

## Características

- **Búsqueda Semántica**: Los usuarios pueden buscar productos usando lenguaje natural
- **Sincronización Automática**: Los productos se sincronizan automáticamente con Gemini al guardar/actualizar
- **Búsqueda con IA**: Respuestas generadas por IA con recomendaciones de productos
- **API REST**: Endpoints disponibles para integración personalizada
- **Panel de Administración**: Interfaz completa para configuración y gestión
- **Shortcodes**: Fácil integración en páginas y posts
- **Historial de Sincronización**: Registro de todas las sincronizaciones realizadas

## Requisitos

- WordPress 5.8 o superior
- WooCommerce 5.0 o superior
- PHP 7.4 o superior
- API Key de Google Gemini

## Instalación

1. Copia la carpeta `google-file-search` en `/wp-content/plugins/`
2. Activa el plugin desde el panel de WordPress
3. Ve a **Gemini Search > Configuración**
4. Configura tu API Key de Google Gemini
5. Crea un nuevo Corpus
6. Sincroniza tus productos

## Configuración Inicial

### 1. Obtener API Key de Gemini

1. Ve a [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Crea una nueva API Key
3. Copia la API Key

### 2. Configurar el Plugin

1. En WordPress, ve a **Gemini Search > Configuración**
2. Pega tu API Key en el campo correspondiente
3. Haz clic en "Probar Conexión" para verificar
4. Haz clic en "Crear Nuevo Corpus" para generar un corpus
5. Guarda los cambios

### 3. Sincronizar Productos

1. Ve a **Gemini Search > Sincronización**
2. Haz clic en "Sincronizar Todos los Productos"
3. Espera a que se complete el proceso

## Uso

### Shortcodes

#### Búsqueda Básica

```php
[gfs_search]
```

Parámetros opcionales:
- `placeholder`: Texto del placeholder (default: "Buscar productos...")
- `button_text`: Texto del botón (default: "Buscar")
- `results_per_page`: Número de resultados (default: 10)

Ejemplo:
```php
[gfs_search placeholder="¿Qué estás buscando?" button_text="Buscar ahora" results_per_page="20"]
```

#### Búsqueda con IA

```php
[gfs_ai_search]
```

Parámetros opcionales:
- `placeholder`: Texto del placeholder
- `button_text`: Texto del botón

Ejemplo:
```php
[gfs_ai_search placeholder="Describe lo que necesitas..." button_text="Buscar con IA"]
```

### API REST

#### Búsqueda Simple

```
GET /wp-json/gfs/v1/search?query=camiseta&limit=10
```

Parámetros:
- `query` (requerido): Consulta de búsqueda
- `limit` (opcional): Número de resultados (default: 10)

Respuesta:
```json
{
  "success": true,
  "query": "camiseta",
  "total_results": 5,
  "products": [
    {
      "id": 123,
      "name": "Camiseta Básica",
      "sku": "CAM-001",
      "price": "19.99",
      "price_html": "<span class='amount'>$19.99</span>",
      "description": "Camiseta de algodón 100%...",
      "permalink": "https://example.com/producto/camiseta",
      "image": "https://example.com/wp-content/uploads/...",
      "stock_status": "instock",
      "in_stock": true,
      "categories": ["Ropa", "Hombre"],
      "tags": ["básico", "algodón"],
      "relevance_score": 0.95
    }
  ]
}
```

#### Búsqueda con IA

```
POST /wp-json/gfs/v1/search-ai
Content-Type: application/json

{
  "query": "Necesito un regalo para mi madre"
}
```

Respuesta:
```json
{
  "success": true,
  "query": "Necesito un regalo para mi madre",
  "ai_response": "Basado en tu búsqueda, te recomiendo estos productos que serían excelentes regalos...",
  "products": [...],
  "grounding_metadata": {...}
}
```

## Sincronización

### Automática

Por defecto, los productos se sincronizan automáticamente cuando:
- Se crea un nuevo producto
- Se actualiza un producto existente
- Se elimina un producto

Puedes desactivar la sincronización automática en **Configuración**.

### Manual

Para sincronizar manualmente:
1. Ve a **Gemini Search > Sincronización**
2. Haz clic en "Sincronizar Todos los Productos"

### Mediante Código

```php
// Sincronizar un producto específico
$product_sync = GFS_Product_Sync::get_instance();
$result = $product_sync->sync_product($product_id);

// Sincronizar todos los productos
$results = $product_sync->bulk_sync_all_products();
```

## Pruebas

### Probar Búsqueda

1. Ve a **Gemini Search > Prueba de Búsqueda**
2. Usa el campo de búsqueda simple o la búsqueda con IA
3. Verifica los resultados

### Ejemplos de Búsquedas

Búsqueda simple:
- "camiseta roja"
- "zapatos deportivos"
- "regalo cumpleaños"

Búsqueda con IA:
- "Necesito un regalo para mi padre que le gusta el deporte"
- "Busco ropa cómoda para hacer ejercicio"
- "Quiero decorar mi jardín con plantas"

## Estructura de Archivos

```
google-file-search/
├── google-file-search.php       # Archivo principal del plugin
├── includes/
│   ├── class-gemini-client.php  # Cliente de API de Gemini
│   ├── class-product-sync.php   # Sincronización de productos
│   ├── class-search-api.php     # Endpoints de búsqueda REST
│   ├── class-admin.php          # Panel de administración
│   └── class-frontend.php       # Shortcodes y frontend
├── assets/
│   ├── css/
│   │   ├── admin.css           # Estilos del admin
│   │   └── frontend.css        # Estilos del frontend
│   └── js/
│       ├── admin.js            # JavaScript del admin
│       └── frontend.js         # JavaScript del frontend
└── README.md
```

## Hooks y Filtros

### Actions

```php
// Después de sincronizar un producto
do_action('gfs_product_synced', $product_id, $document_id);

// Antes de eliminar sincronización
do_action('gfs_before_delete_sync', $product_id, $document_id);
```

### Filters

```php
// Modificar contenido del producto antes de sincronizar
add_filter('gfs_product_content', function($content, $product) {
    // Modificar contenido
    return $content;
}, 10, 2);

// Modificar límite de resultados de búsqueda
add_filter('gfs_search_limit', function($limit) {
    return 20;
});
```

## Solución de Problemas

### La conexión falla

- Verifica que tu API Key sea correcta
- Asegúrate de tener acceso a la API de Gemini
- Verifica tu conexión a internet

### Los productos no se sincronizan

- Verifica que el corpus esté creado
- Comprueba los logs en **Sincronización**
- Asegúrate de que los productos estén publicados

### La búsqueda no devuelve resultados

- Verifica que los productos estén sincronizados
- Espera unos minutos después de sincronizar (indexación)
- Prueba con términos más generales

### Límites de la API

El plugin respeta los límites de la API de Gemini:
- Tamaño máximo de archivo: 100 MB por documento
- Se recomienda mantener corpus bajo 20 GB para mejor rendimiento
- Hay un pequeño delay entre sincronizaciones para evitar límites de tasa

## Desarrollo

### Requisitos de Desarrollo

- Node.js (opcional, para compilar assets)
- Conocimiento de WordPress Plugin API
- Conocimiento de WooCommerce

### Contribuir

1. Fork el repositorio
2. Crea una rama para tu feature
3. Commit tus cambios
4. Push a la rama
5. Crea un Pull Request

## Licencia

GPL v2 or later

## Soporte

Para soporte y preguntas:
- Documentación: [Google Gemini File Search](https://ai.google.dev/gemini-api/docs/file-search)
- Issues: Reporta bugs en el repositorio del proyecto

## Changelog

### 1.0.0
- Versión inicial
- Búsqueda semántica básica
- Búsqueda con IA
- Sincronización automática
- Panel de administración
- API REST
- Shortcodes

## Créditos

- Desarrollado usando [Google Gemini API](https://ai.google.dev/)
- Compatible con [WooCommerce](https://woocommerce.com/)

## Seguridad

- Todas las consultas están sanitizadas
- Se utilizan nonces para validación AJAX
- La API Key se almacena de forma segura en la base de datos
- Se previenen ataques XSS en la salida

## Rendimiento

- Búsquedas optimizadas con caché
- Sincronización por lotes con delays para evitar timeouts
- Carga condicional de scripts solo donde se necesitan
- Queries optimizadas en la base de datos
