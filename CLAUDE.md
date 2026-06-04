# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

`tiny_teamsmeeting` is a Moodle TinyMCE editor plugin (package `tiny_teamsmeeting`, versión `2025100205`) que integra la creación de reuniones de Microsoft Teams en la barra de herramientas del editor Tiny. Soporta Moodle 4.0.1+ hasta 5.0.x y requiere PHP 7.4–8.4.

## Build & Development Commands

JavaScript source en `amd/src/`; compilado en `amd/build/`. Recompilar tras editar cualquier JS:

```bash
# Desde la raíz de Moodle
grunt amd --root=lib/editor/tiny/plugins/teamsmeeting
# O para observar cambios:
grunt watch --root=lib/editor/tiny/plugins/teamsmeeting
```

Lint JS:
```bash
npx eslint amd/src/
```

PHP lint:
```bash
php -l classes/plugininfo.php
php -l classes/external/get_meeting_details.php
php -l classes/form/edit_session_form.php
```

Tras cambios en clases PHP, capacidades, servicios o esquema DB, ejecutar el upgrade:
```bash
php admin/cli/upgrade.php --non-interactive
# o visitar /admin/index.php?cache=1
```

Limpiar caché de idioma tras cambios en `lang/`:
```bash
php admin/cli/purge_caches.php
```

## Running Tests

PHPUnit (desde la raíz de Moodle):
```bash
# Todas las pruebas del plugin
vendor/bin/phpunit --testsuite tiny_teamsmeeting_testsuite

# Clase individual
vendor/bin/phpunit lib/editor/tiny/plugins/teamsmeeting/tests/webservice_test.php

# Método individual
vendor/bin/phpunit --filter test_teamsmeeting_capability_with_access lib/editor/tiny/plugins/teamsmeeting/tests/webservice_test.php
```

Behat (desde la raíz de Moodle):
```bash
php admin/tool/behat/cli/run.php --tags=@tiny_teamsmeeting --profile=chrome
```

## Architecture

### Data flow — creación de reunión

1. **PHP → JS**: `plugininfo::get_plugin_configuration_for_context()` retorna `appurl`, `clientdomain`, `localevalue`, `msession`, `courseid`. Registrados en `amd/src/options.js`, leídos en `amd/src/commands.js`.

2. **Botón en toolbar** (`amd/src/commands.js`): Abre un modal con un `<iframe>` que carga la Microsoft Meetings App (URL configurable en ajustes admin, por defecto `https://enomsteams.z16.web.core.windows.net`).

3. **postMessage cross-origin**: Al crear la reunión en el iframe, la Meetings App envía `{ action: 'meetingUrl', url: '...' }` al padre. `commands.js` lo escucha y rellena el campo URL en el diálogo.

4. **result.php**: Target del iframe tras crear la reunión. Guarda `title`, `link`, `options`, `starttime`, `endtime` en la tabla `{tiny_teamsmeeting}`, luego hace `postMessage` con la URL de vuelta al padre.

5. **Web service** (`tiny_teamsmeeting_get_meeting_details`): Llamado vía AJAX cuando el usuario clica un enlace de reunión ya creado. Busca el registro en DB y retorna redirect a `result.php` (modo `viewexisting`) para pre-poblar el diálogo.

### Link identification strategy

Las reuniones en el contenido del editor se identifican en orden de prioridad:
1. Atributo `data-teams-meeting` (marcador actual; el DOM lo preserva pero HTML Purifier lo elimina al guardar en servidor)
2. `id="tiny_meeting_link"` (marcador legacy)
3. Patrón URL `/^https:\/\/teams\.microsoft\.com\/l\/meetup-join\//i` (fallback tras filtrado en servidor)

En `init` del editor, `migrateLegacyLinks()` convierte todos los enlaces reconocidos a `data-teams-meeting`. El atributo se inyecta en el schema de TinyMCE en `PreInit` para evitar que el propio editor lo elimine.

### Admin report (report.php)

Vista de calendario mensual que muestra todas las sesiones registradas:
- **Morado** (`#6264a7`): reuniones con `starttime` definido, se ubican en su día programado.
- **Gris** (`#adb5bd`): reuniones sin `starttime`, se ubican en el día de `timecreated`.
- Navegación mes a mes con parámetros `year` y `month`.
- Descarga Excel vía `?download=excel` usando `\core\dataformat::download_data()` con formato `excel`.

### Edit session (edit_session.php + classes/form/edit_session_form.php)

Permite editar `title`, `starttime` y `endtime` de una sesión existente. El campo `link` se muestra como texto estático (solo lectura) y **nunca** se incluye en el `update_record`, por lo que es imposible modificarlo accidentalmente.

### DB schema — tabla `{tiny_teamsmeeting}`

| Campo | Tipo | Notas |
|-------|------|-------|
| `id` | INT | PK |
| `title` | VARCHAR(255) | Título de la reunión |
| `link` | TEXT | URL de acceso a Teams (inmutable tras creación) |
| `options` | TEXT | URL de opciones de la reunión |
| `timecreated` | INT | Timestamp Unix de cuando se guardó el registro |
| `starttime` | INT | Timestamp Unix del inicio programado (nullable) |
| `endtime` | INT | Timestamp Unix del fin programado (nullable) |

`starttime` y `endtime` fueron añadidos en versión `2025100205` vía `db/upgrade.php`. Registros anteriores tienen `NULL` en esos campos.

### Key files

| Archivo | Propósito |
|---------|-----------|
| `classes/plugininfo.php` | Registro PHP del plugin; botones, comprobación de capacidad y config JS |
| `classes/external/get_meeting_details.php` | Web service: busca reunión por URL, retorna redirect a result.php |
| `classes/form/edit_session_form.php` | Formulario Moodle para editar sesión (título + horario, sin link) |
| `amd/src/plugin.js` | Entry point JS; registra el plugin en TinyMCE |
| `amd/src/commands.js` | Botón toolbar, diálogo, detección/migración de enlaces |
| `amd/src/options.js` | Registro de opciones TinyMCE y getters |
| `amd/src/configuration.js` | Añade el botón al grupo `content` de la toolbar |
| `result.php` | Target del iframe: guarda reunión en DB, renderiza confirmación, postMessage |
| `report.php` | Reporte admin: calendario mensual de sesiones + descarga Excel |
| `edit_session.php` | Página admin para editar título y horario de una sesión |
| `error.php` | Target del iframe para estado de error |
| `db/install.xml` | Schema completo (instalaciones nuevas) |
| `db/upgrade.php` | Migraciones (añade starttime/endtime a instalaciones existentes) |
| `db/services.php` | Definición del web service y service set |
| `db/access.php` | Define la capacidad `tiny/teamsmeeting:add` |
| `settings.php` | Ajuste admin `meetingapplink` + registro de página del reporte |
| `lang/es/` | Strings en español (idioma principal) |
| `lang/en/` | Strings en español como fallback universal |

### Capability

`tiny/teamsmeeting:add` — concedida a `editingteacher` por defecto; controla tanto la visibilidad del plugin en el editor como el acceso a `result.php`.

### External dependency

La app externa de Microsoft Meetings se configura en `tiny_teamsmeeting/meetingapplink` (ajuste admin). Instrucciones de self-hosting en README.MD.
