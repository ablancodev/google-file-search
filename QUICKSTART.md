# Inicio Rápido - 5 Minutos

Esta guía te permite tener el plugin funcionando en menos de 5 minutos.

## Paso 1: Activar el Plugin (30 segundos)

1. El plugin ya está en `/wp-content/plugins/google-file-search`
2. Ve a WordPress → **Plugins**
3. Activa **"Google Gemini File Search for WooCommerce"**

## Paso 2: Obtener API Key (2 minutos)

1. Abre [https://aistudio.google.com/app/apikey](https://aistudio.google.com/app/apikey)
2. Inicia sesión con tu cuenta de Google
3. Haz clic en **"Create API Key"**
4. Copia la clave generada

## Paso 3: Configurar (1 minuto)

1. Ve a **Gemini Search → Configuración**
2. Pega tu API Key
3. Haz clic en **"Probar Conexión"** (debería decir ✓)
4. Haz clic en **"Crear Nuevo Corpus"**
5. Haz clic en **"Guardar cambios"**

## Paso 4: Sincronizar Productos (1-2 minutos)

1. Ve a **Gemini Search → Sincronización**
2. Haz clic en **"Sincronizar Todos los Productos"**
3. Espera a que termine (verás progreso)

## Paso 5: Probar (30 segundos)

### Opción A: En el Admin

1. Ve a **Gemini Search → Prueba de Búsqueda**
2. Escribe una búsqueda (ej: "camiseta roja")
3. Haz clic en **"Buscar"**

### Opción B: En tu Sitio

1. Edita cualquier página
2. Añade este shortcode:
   ```
   [gfs_search]
   ```
3. Publica y visita la página

## ¡Listo! 🎉

Tu búsqueda semántica ya está funcionando.

## Ejemplos de Búsqueda

Prueba estas búsquedas para ver la magia:

**Búsqueda Simple:**
- "zapatos cómodos"
- "regalo cumpleaños"
- "ropa deportiva"

**Búsqueda con IA:**
```
[gfs_ai_search]
```
- "Necesito un regalo para mi madre que le gusta cocinar"
- "Busco ropa para hacer ejercicio en verano"
- "Quiero decorar mi sala de estar con estilo moderno"

## Personalización Rápida

### Cambiar el placeholder del buscador:

```
[gfs_search placeholder="¿Qué estás buscando?"]
```

### Mostrar más resultados:

```
[gfs_search results_per_page="20"]
```

### Cambiar el texto del botón:

```
[gfs_search button_text="Buscar ahora"]
```

### Todo junto:

```
[gfs_search placeholder="Busca tu producto ideal" button_text="🔍 Buscar" results_per_page="15"]
```

## Problemas Comunes

**No encuentra productos:**
- Espera 1-2 minutos después de sincronizar
- Verifica que los productos estén publicados
- Usa términos más generales

**Error de API:**
- Verifica que la API Key sea correcta
- Revisa que el corpus esté creado

**Sincronización lenta:**
- Normal con muchos productos
- Se añade delay de 0.5s entre productos

## Próximos Pasos

Lee la [documentación completa](README.md) para:
- Integración con la API REST
- Personalización avanzada
- Hooks y filtros
- Ejemplos de código

## Shortcuts Útiles

- **Configuración:** Admin → Gemini Search → Configuración
- **Sincronización:** Admin → Gemini Search → Sincronización
- **Pruebas:** Admin → Gemini Search → Prueba de Búsqueda

## Soporte

- 📚 [README completo](README.md)
- 🚀 [Guía de instalación detallada](INSTALL.md)
- 💻 [Ejemplos de código](examples.php)
- 🌐 [Documentación de Gemini](https://ai.google.dev/gemini-api/docs/file-search)

---

**¿Te gusta el plugin?** Compártelo y deja tu feedback.
