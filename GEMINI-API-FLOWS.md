# Flujos de API - Google Gemini File Search

Este documento describe las secuencias de llamadas a la API de Google Gemini File Search que realiza el plugin.

## Configuración Inicial

### 1. Crear File Search Store (Corpus)

**Endpoint:**
```
POST https://generativelanguage.googleapis.com/v1beta/fileSearchStores?key={API_KEY}
```

**Body:**
```json
{
  "displayName": "WooCommerce Products - {SITE_NAME}"
}
```

**Respuesta exitosa:**
```json
{
  "name": "fileSearchStores/woocommerce-products-woo-xyz123"
}
```

**Código:** `class-gemini-client.php` → `create_corpus()`

---

## Sincronización de Productos

### 2. Crear Documento (Producto Nuevo)

Cuando se sincroniza un producto por primera vez:

#### Paso 2.1: Iniciar Upload Resumible

**Endpoint:**
```
POST https://generativelanguage.googleapis.com/upload/v1beta/{STORE_ID}:uploadToFileSearchStore?key={API_KEY}
```

**Headers:**
```
X-Goog-Upload-Protocol: resumable
X-Goog-Upload-Command: start
X-Goog-Upload-Header-Content-Length: {CONTENT_BYTES}
X-Goog-Upload-Header-Content-Type: text/plain
Content-Type: application/json
```

**Body:**
```json
{
  "display_name": "Nombre del Producto (ID: 123)"
}
```

**Respuesta exitosa (Headers):**
```
X-Goog-Upload-Url: https://generativelanguage.googleapis.com/upload/v1beta/...
```

#### Paso 2.2: Subir Contenido del Documento

**Endpoint:**
```
POST {X-Goog-Upload-Url obtenida en paso anterior}
```

**Headers:**
```
X-Goog-Upload-Command: upload, finalize
X-Goog-Upload-Offset: 0
Content-Type: text/plain
```

**Body (texto plano):**
```
Nombre del producto: Mueble de televisión
Descripción: Un hermoso mueble para tu TV
Precio: $299.99
SKU: MUEBLE-TV-001
Categorías: Muebles, Hogar
URL: https://example.com/producto/mueble-tv
ID del producto: 123
```

**Respuesta exitosa:**
```json
{
  "name": "fileSearchStores/woocommerce-products-woo-xyz123/upload/operations/doc-abc123"
}
```

**Código:** `class-gemini-client.php` → `create_document()`

---

### 3. Actualizar Documento (Producto Existente)

Cuando se actualiza un producto que ya está sincronizado:

#### Paso 3.1: Intentar Eliminar Documento Anterior

**Endpoint:**
```
DELETE https://generativelanguage.googleapis.com/v1beta/{DOCUMENT_ID}?key={API_KEY}
```

**Ejemplo de DOCUMENT_ID:**
```
fileSearchStores/woocommerce-products-woo-xyz123/upload/operations/doc-abc123
```

**Respuesta exitosa:**
```json
{
  "success": true
}
```

**Nota:** Si el documento no existe (404), se ignora el error y se continúa.

#### Paso 3.2: Crear Nuevo Documento

Se repiten los pasos 2.1 y 2.2 para crear el documento actualizado.

**Código:** `class-gemini-client.php` → `update_document()`

---

## Búsqueda de Productos

### 4. Búsqueda Simple (Semantic Search)

**Endpoint:**
```
POST https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={API_KEY}
```

**Body:**
```json
{
  "contents": [
    {
      "parts": [
        {
          "text": "Lista los productos más relevantes para: mueble"
        }
      ]
    }
  ],
  "tools": [
    {
      "file_search": {
        "file_search_store_names": [
          "fileSearchStores/woocommerce-products-woo-xyz123"
        ]
      }
    }
  ]
}
```

**Respuesta exitosa:**
```json
{
  "candidates": [
    {
      "content": {
        "parts": [
          {
            "text": "Respuesta generada por el AI..."
          }
        ],
        "role": "model"
      },
      "groundingMetadata": {
        "groundingChunks": [
          {
            "retrievedContext": {
              "text": "Nombre del producto: Mueble de televisión\n..."
            }
          }
        ]
      }
    }
  ]
}
```

**Procesamiento:**
1. Se extraen los `groundingChunks`
2. Por cada chunk, se extrae el `Product ID` del texto
3. Se recuperan los datos completos del producto desde WooCommerce
4. Se eliminan duplicados usando `$seen_ids`

**Código:** 
- `class-gemini-client.php` → `query_corpus()`
- `class-search-api.php` → `search()` → `process_search_results()`

---

### 5. Búsqueda con IA (AI-Powered Search)

**Endpoint:**
```
POST https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={API_KEY}
```

**Body:**
```json
{
  "contents": [
    {
      "parts": [
        {
          "text": "Busca productos que coincidan con esta consulta: 'regalo para mi madre'. Proporciona una respuesta útil que incluya los productos más relevantes..."
        }
      ]
    }
  ],
  "tools": [
    {
      "file_search": {
        "file_search_store_names": [
          "fileSearchStores/woocommerce-products-woo-xyz123"
        ]
      }
    }
  ]
}
```

**Respuesta exitosa:**
```json
{
  "candidates": [
    {
      "content": {
        "parts": [
          {
            "text": "Para un regalo para tu madre, te recomiendo estos productos: 1) Mueble de televisión..."
          }
        ]
      },
      "groundingMetadata": {
        "groundingChunks": [
          {
            "retrievedContext": {
              "text": "Nombre del producto: Mueble de televisión..."
            }
          }
        ]
      }
    }
  ]
}
```

**Procesamiento:**
1. Se extrae la respuesta de texto del IA
2. Se extraen los `groundingChunks`
3. Por cada chunk, se extrae el `Product ID`
4. Se recuperan los datos del producto
5. Se eliminan duplicados
6. Se devuelve tanto la respuesta del IA como los productos relacionados

**Código:**
- `class-gemini-client.php` → `generate_with_search()`
- `class-search-api.php` → `search_with_ai()`

---

## Eliminación de Productos

### 6. Eliminar Documento

**⚠️ LIMITACIÓN IMPORTANTE:** La API de File Search Stores **NO permite eliminar documentos individuales**.

Cuando se elimina un producto de WooCommerce:
- Se limpia el metadata local (`_gfs_document_id`)
- Se limpia el registro en la tabla de sync log
- **El documento permanece en el File Search Store**

Los documentos solo se pueden eliminar de dos formas:
1. **Eliminando el store completo** (DELETE del fileSearchStore)
2. **Esperando que expiren** (Google puede tener políticas de expiración automática)

**Solución recomendada:**
- Cuando actualices productos, se crearán nuevos documentos
- Los documentos antiguos permanecerán pero no afectarán las búsquedas
- Periódicamente, elimina el store completo y crea uno nuevo para limpiar

**Código:**
- `class-gemini-client.php` → `delete_document()` - Retorna error informativo
- `class-product-sync.php` → `delete_product_sync()` - Solo limpia registros locales

---

## Notas Importantes

### Modelo Compatible
- ✅ **gemini-2.5-flash** - Soporta file_search
- ❌ **gemini-2.0-flash-exp** - NO soporta file_search

### Formato de Tool
Debe usarse **snake_case** (no camelCase):
```json
{
  "file_search": {
    "file_search_store_names": ["..."]
  }
}
```

### Estructura de Document ID
```
fileSearchStores/{store-id}/upload/operations/{operation-id}
```

Ejemplo completo:
```
fileSearchStores/woocommerce-products-woo-xyz123/upload/operations/mueble-id-14-abc123
```

### Migración desde Corpora (API Antigua)
La API antigua usaba:
```
corpora/{corpus-id}/documents/{document-id}
```

Esta estructura ya no es válida. El plugin detecta automáticamente IDs antiguos y los ignora durante actualizaciones.

---

## Resumen de Endpoints

| Operación | Endpoint | Método |
|-----------|----------|--------|
| Crear Store | `/v1beta/fileSearchStores` | POST |
| Iniciar Upload | `/upload/v1beta/{store}:uploadToFileSearchStore` | POST |
| Subir Contenido | URL devuelta en init | POST |
| Buscar | `/v1beta/models/gemini-2.5-flash:generateContent` | POST |
| Eliminar Documento | `/v1beta/{document_id}` | DELETE |

---

## Diagrama de Flujo: Sincronización de Producto

```
┌─────────────────────────────────────┐
│ Usuario guarda/actualiza producto   │
└───────────────┬─────────────────────┘
                │
                ▼
┌─────────────────────────────────────┐
│ ¿Producto tiene _gfs_document_id?   │
└───────┬─────────────────────┬───────┘
        │ NO                  │ SÍ
        ▼                     ▼
┌──────────────┐     ┌─────────────────┐
│ create_doc() │     │ update_doc()    │
└──────┬───────┘     └────────┬────────┘
       │                      │
       │              ┌───────▼────────┐
       │              │ DELETE antiguo │
       │              └───────┬────────┘
       │                      │
       └──────────┬───────────┘
                  ▼
        ┌──────────────────┐
        │ Init Upload      │
        │ (resumable)      │
        └─────────┬────────┘
                  ▼
        ┌──────────────────┐
        │ Upload Content   │
        └─────────┬────────┘
                  ▼
        ┌──────────────────┐
        │ Guardar doc_id   │
        │ en product meta  │
        └──────────────────┘
```

## Diagrama de Flujo: Búsqueda

```
┌────────────────────────┐
│ Usuario busca "mueble" │
└───────────┬────────────┘
            ▼
┌──────────────────────────┐
│ POST generateContent     │
│ con file_search tool     │
└───────────┬──────────────┘
            ▼
┌──────────────────────────┐
│ Gemini busca en Store    │
│ y devuelve chunks        │
└───────────┬──────────────┘
            ▼
┌──────────────────────────┐
│ Extraer groundingChunks  │
└───────────┬──────────────┘
            ▼
┌──────────────────────────┐
│ Por cada chunk:          │
│ - Extraer Product ID     │
│ - Obtener datos WC       │
│ - Evitar duplicados      │
└───────────┬──────────────┘
            ▼
┌──────────────────────────┐
│ Devolver productos       │
│ al frontend              │
└──────────────────────────┘
```

---

---

## Gestión de Stores

### 7. Listar File Search Stores

**Endpoint:**
```
GET https://generativelanguage.googleapis.com/v1beta/fileSearchStores?key={API_KEY}
```

**Respuesta exitosa:**
```json
{
  "fileSearchStores": [
    {
      "name": "fileSearchStores/woocommerce-products-woo-xyz123",
      "displayName": "WooCommerce Products - Mi Tienda",
      "createTime": "2025-11-23T10:00:00Z"
    }
  ]
}
```

### 8. Eliminar File Search Store

**Endpoint:**
```
DELETE https://generativelanguage.googleapis.com/v1beta/{STORE_ID}?key={API_KEY}
```

**Ejemplo:**
```
DELETE https://generativelanguage.googleapis.com/v1beta/fileSearchStores/woocommerce-products-woo-xyz123?key={API_KEY}
```

**Respuesta exitosa:**
```json
{
  "success": true
}
```

**Nota:** Esto eliminará el store y **todos** sus documentos.

---

## Estrategia de Limpieza Recomendada

Dado que no se pueden eliminar documentos individuales:

1. **Para pruebas/desarrollo:**
   - Crea un store de prueba
   - Cuando quieras limpiar, elimínalo y crea uno nuevo
   - Actualiza el `gfs_corpus_id` en la configuración

2. **Para producción:**
   - Usa un store permanente
   - Las actualizaciones de productos crearán nuevos documentos
   - Los documentos antiguos no interferirán con las búsquedas
   - Cada 6-12 meses, considera crear un store nuevo y migrar

3. **Workflow de migración:**
   ```
   1. Crear nuevo store
   2. Actualizar gfs_corpus_id
   3. Sincronizar todos los productos
   4. Verificar que búsquedas funcionan
   5. Eliminar store antiguo
   ```

---

## Autor
Plugin: Google Gemini File Search for WooCommerce
Version: 1.0.2
