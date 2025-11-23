# Guía de Instalación - Google Gemini File Search for WooCommerce

## Requisitos Previos

Antes de instalar el plugin, asegúrate de tener:

1. **WordPress** 5.8 o superior
2. **WooCommerce** 5.0 o superior instalado y activo
3. **PHP** 7.4 o superior
4. **API Key de Google Gemini** (obtén una en https://aistudio.google.com/app/apikey)

## Paso 1: Instalación del Plugin

### Opción A: Instalación Manual

1. Descarga o clona este repositorio
2. Copia la carpeta `google-file-search` completa a:
   ```
   /wp-content/plugins/
   ```
3. Ve al panel de WordPress → Plugins
4. Busca "Google Gemini File Search for WooCommerce"
5. Haz clic en "Activar"

### Opción B: Via ZIP (si tienes el archivo comprimido)

1. Ve a WordPress → Plugins → Añadir nuevo
2. Haz clic en "Subir plugin"
3. Selecciona el archivo ZIP
4. Haz clic en "Instalar ahora"
5. Activa el plugin

## Paso 2: Obtener API Key de Google Gemini

1. Ve a [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Inicia sesión con tu cuenta de Google
3. Haz clic en "Create API Key"
4. Selecciona o crea un proyecto de Google Cloud
5. Copia la API Key generada (guárdala en un lugar seguro)

**Nota importante sobre la API Key:**
- No compartas tu API Key públicamente
- La API de Gemini tiene límites de uso según tu plan
- Revisa los [términos de uso de Google](https://ai.google.dev/gemini-api/terms)

## Paso 3: Configuración Inicial

### 3.1 Acceder a la Configuración

1. En el panel de WordPress, ve a **Gemini Search → Configuración**
2. Verás la página de configuración del plugin

### 3.2 Configurar API Key

1. Pega tu API Key en el campo "API Key de Gemini"
2. Haz clic en el botón "Probar Conexión"
3. Deberías ver un mensaje "✓ Conexión exitosa"
   - Si ves un error, verifica que la API Key sea correcta

### 3.3 Crear Corpus

El corpus es el contenedor donde se almacenarán los embeddings de tus productos.

1. Haz clic en "Crear Nuevo Corpus"
2. Se generará automáticamente un Corpus ID
3. El ID aparecerá en el campo "Corpus ID"
4. Haz clic en "Guardar cambios"

**Nota:** Solo necesitas crear el corpus una vez. Si ya tienes un corpus ID de una instalación anterior, puedes pegarlo directamente.

### 3.4 Opciones de Sincronización

- **Sincronización Automática:** Marca esta opción para que los productos se sincronicen automáticamente al guardar/actualizar
  - Recomendado: Activado para mantener productos siempre actualizados

## Paso 4: Sincronización Inicial de Productos

### 4.1 Sincronización Masiva

1. Ve a **Gemini Search → Sincronización**
2. Haz clic en "Sincronizar Todos los Productos"
3. Espera a que se complete el proceso
   - Verás una barra de progreso
   - El tiempo depende del número de productos
   - Aproximadamente: 1-2 segundos por producto

**Importante:**
- La primera sincronización puede tardar varios minutos si tienes muchos productos
- No cierres la ventana durante la sincronización
- Se añade un pequeño delay entre productos para evitar límites de la API

### 4.2 Verificar Sincronización

1. En la misma página, verás el "Historial de Sincronización"
2. Revisa que los productos tengan estado "Success"
3. Si hay errores, revisa el mensaje de error en la tabla

### 4.3 Sincronización Individual

También puedes sincronizar productos individualmente:
- Edita cualquier producto en WooCommerce
- Haz clic en "Actualizar" o "Publicar"
- El producto se sincronizará automáticamente (si la opción está activada)

## Paso 5: Probar la Búsqueda

### 5.1 Prueba en el Panel Admin

1. Ve a **Gemini Search → Prueba de Búsqueda**

2. **Búsqueda Simple:**
   - Ingresa una consulta (ej: "camiseta roja")
   - Haz clic en "Buscar"
   - Verás los resultados con puntuación de relevancia

3. **Búsqueda con IA:**
   - Ingresa una consulta en lenguaje natural (ej: "Necesito un regalo para mi madre")
   - Haz clic en "Buscar con IA"
   - Verás una respuesta generada por IA y productos relacionados

### 5.2 Añadir Búsqueda al Frontend

#### Usando Shortcodes

**Búsqueda Simple:**
1. Edita cualquier página o post
2. Añade el shortcode:
   ```
   [gfs_search]
   ```
3. Publica la página

**Búsqueda con IA:**
1. Edita cualquier página o post
2. Añade el shortcode:
   ```
   [gfs_ai_search]
   ```
3. Publica la página

#### Personalizar Shortcodes

```php
// Búsqueda simple personalizada
[gfs_search placeholder="¿Qué buscas?" button_text="Buscar" results_per_page="20"]

// Búsqueda con IA personalizada
[gfs_ai_search placeholder="Describe tu búsqueda..." button_text="Buscar con IA"]
```

#### Usando PHP (en templates)

```php
<?php echo do_shortcode('[gfs_search]'); ?>
```

## Paso 6: Integración con la API REST

Si quieres integrar la búsqueda en tu propio código JavaScript:

### Búsqueda Simple

```javascript
fetch('/wp-json/gfs/v1/search?query=camiseta&limit=10')
  .then(response => response.json())
  .then(data => {
    console.log(data.products);
  });
```

### Búsqueda con IA

```javascript
fetch('/wp-json/gfs/v1/search-ai', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce // Necesitas el nonce de WP
  },
  body: JSON.stringify({
    query: 'Necesito un regalo'
  })
})
.then(response => response.json())
.then(data => {
  console.log(data.ai_response);
  console.log(data.products);
});
```

## Verificación de la Instalación

Marca cada ítem cuando lo completes:

- [ ] WordPress y WooCommerce están instalados y activos
- [ ] Plugin activado correctamente
- [ ] API Key configurada y conexión probada
- [ ] Corpus creado
- [ ] Productos sincronizados exitosamente
- [ ] Búsqueda probada en el panel admin
- [ ] Shortcode añadido a una página de prueba
- [ ] Búsqueda funciona correctamente en el frontend

## Solución de Problemas Comunes

### Error: "No se ha configurado la API key de Gemini"

**Solución:** Ve a Configuración y asegúrate de que la API Key esté guardada correctamente.

### Error: "No se ha configurado el corpus de Gemini"

**Solución:** Haz clic en "Crear Nuevo Corpus" en la página de configuración.

### Los productos no se sincronizan

**Causas posibles:**
1. Corpus no creado → Crea el corpus
2. API Key incorrecta → Verifica la key
3. Productos no publicados → Solo se sincronizan productos con estado "publish"
4. Límite de API alcanzado → Espera unos minutos y reintenta

### La búsqueda no devuelve resultados

**Soluciones:**
1. Espera 1-2 minutos después de sincronizar (tiempo de indexación)
2. Verifica que los productos estén sincronizados (Sincronización → Historial)
3. Prueba con términos más generales
4. Revisa que el corpus tenga productos

### Error 500 en sincronización masiva

**Soluciones:**
1. Aumenta los límites de PHP:
   ```
   max_execution_time = 300
   memory_limit = 256M
   ```
2. Sincroniza productos en lotes más pequeños
3. Verifica los logs de WordPress

## Límites y Consideraciones

### Límites de la API de Gemini

- **Tamaño máximo por documento:** 100 MB
- **Almacenamiento recomendado por corpus:** < 20 GB para mejor rendimiento
- **Rate limits:** Varían según tu plan de Google Cloud

### Límites del Plugin

- **Delay entre sincronizaciones:** 0.5 segundos (configurable en código)
- **Timeout de sincronización:** 5 minutos
- **Resultados de búsqueda:** Hasta 10 por defecto (configurable)

## Mantenimiento

### Sincronización Regular

- La sincronización automática mantiene los productos actualizados
- Puedes ejecutar sincronización masiva periódicamente para asegurar consistencia

### Monitoreo

- Revisa regularmente el "Historial de Sincronización"
- Verifica que no haya errores acumulados

### Actualizaciones

- Mantén el plugin actualizado
- Revisa el changelog para nuevas funcionalidades

## Soporte

Si tienes problemas durante la instalación:

1. Revisa la [documentación completa](README.md)
2. Verifica los [requisitos del sistema](#requisitos-previos)
3. Consulta la [documentación de Gemini API](https://ai.google.dev/gemini-api/docs/file-search)
4. Reporta issues en el repositorio del proyecto

## Próximos Pasos

Una vez instalado y configurado:

1. Personaliza los estilos CSS según tu tema
2. Ajusta los shortcodes según tus necesidades
3. Integra la búsqueda en tu tema personalizado
4. Configura hooks personalizados si es necesario
5. Considera implementar caché para mejor rendimiento

¡Felicitaciones! Tu búsqueda semántica está lista para usar. 🎉
