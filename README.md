# ShAdmin web

Frontend Laravel + Livewire de la API Shadai. El navegador se conecta a este servidor y el servidor realiza las peticiones a la API; las credenciales compartidas y el token remoto permanecen en el servidor. Ya no requiere NativePHP ni Electron. Los tickets se imprimen con el diálogo del navegador.

## Conexión local

La API auditada está en `C:\Users\USER\Documents\lv11\apishadai`. Su URL se configura por separado de la URL del frontend:

```dotenv
APP_URL=http://localhost:8001
DB_CONNECTION=none
SESSION_DRIVER=file
SESSION_ENCRYPT=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
SHADMIN_API_URL=http://localhost:8000/api
SHADMIN_AUTH_LOGIN_PATH=/auth/session/login
SHADMIN_AUTH_LOGOUT_PATH=/auth/session/logout
SHADMIN_DEVICE_HEADER_SECRET=
SHADMIN_DEVICE_NAME=SHADMIN-WEB
SHADMIN_APP_VERSION=1.0.0
SHADMIN_API_CONNECT_TIMEOUT=5
SHADMIN_API_TIMEOUT=30
```

- `SHADMIN_DEVICE_HEADER_SECRET`: debe coincidir con la clave que usa `SingleHashRepository` en la API (actualmente su `APP_KEY`, incluyendo el prefijo `base64:`). La clave `APP_KEY` propia del frontend se mantiene independiente. Nunca publiques estos valores.
- El frontend envía el nombre del dispositivo y la IP de la petición web en los encabezados que espera la API. Si no se define `SHADMIN_DEVICE_NAME`, usa `SHADMIN-WEB`. `SHADMIN_DEVICE_IP` es opcional y permite fijar una IP cuando la instancia web opera detrás de un proxy o representa una sucursal completa.
- Si la identidad aún no está registrada, la API guarda el dispositivo desconocido y responde con un error de registro. El administrador puede darlo de alta y asociarle un stock; después se puede iniciar sesión con esa identidad.
- `SHADMIN_APP_VERSION`: versión que debe aceptar la API cuando su middleware de versiones esté habilitado.

Después de editar el entorno, ejecuta `php artisan config:clear`.

La captura automática de consola de Boost está desactivada por defecto (`BOOST_BROWSER_LOGS_WATCHER=false`) para probar la navegación de Livewire sin las peticiones adicionales a `/_boost/browser-logs`. Para capturar logs durante un diagnóstico, activa esa variable, limpia la configuración y recarga completamente el navegador. El endpoint de logs acepta únicamente POST; abrirlo como una página mediante GET produce un error 405. La navegación y su indicador de progreso siguen a cargo de Livewire.

Con `SHADMIN_API_URL` definido, las cuentas se administran en Configuración > Usuarios mediante la API. El frontend reconstruye el usuario de la sesión emitida por la API y guarda su token en una sesión web cifrada en archivos; no necesita conexión SQL ni tabla de usuarios propia. El registro público, recuperación local, passkeys y ajustes locales de credenciales quedan deshabilitados. El modo sin API conserva la autenticación local para pruebas y desarrollo con una base configurada aparte.

## Ejecutar

```sh
composer install
npm ci
npm run build
php artisan serve --port=8001
```

En una instalación nueva genera `APP_KEY`. El frontend conectado a la API no necesita migraciones ni una base SQL. Su sesión web cifrada se guarda en `storage/framework/sessions`; el backend registra y valida los tokens, dispositivos y datos de negocio.

El servidor necesita las extensiones requeridas por Composer, incluida GD para imágenes, y memoria suficiente para procesar fotografías de documentos (se verificó con `memory_limit=512M`). En producción sirve la carpeta `public`, configura HTTPS y `APP_DEBUG=false`, y compila los recursos con Vite. Los directorios generados `nativephp/` y `tmp/` no forman parte del despliegue web.

## Contratos revisados

Los prefijos siguientes son relativos a `SHADMIN_API_URL`. Se conservaron los nombres que realmente registra el backend, incluso los prefijos anidados heredados.

| Pantalla o servicio | Prefijo / contrato |
| --- | --- |
| Acceso y cierre de sesión | `/auth/session` |
| Jornada y almuerzo | `/auth/worklunch` |
| Usuarios, roles y estados | `/config/user` |
| Horarios por usuario | `/config/horas-lab/find/{usuario}` y `POST /save` |
| Módulos y vistas | `/config/modulo`, `/config/vista`; CRUD en español |
| Permisos, sucursales y dispositivos | `/config/permisos`, `/config/stock`, `/config/device` |
| Sorteos y tickets | `/sorteos/sorteo`, `/sorteos/ticket` |
| Reportes y exportaciones | `/reportes/sorteos`, `/reportes/worklunch` |
| Proveedores y laboratorios | `/farma/proveedores`, `/farma/laboratorios` |
| Productos y selectores compartidos | `/farma/productos/producto` |
| Unidades, estados, categorías, presentaciones y administraciones | `/farma/productos/{catalogo}`; `PUT /actualizar/{id}` |
| Familias y principios activos | `/farma/productos/{catalogo}`; `PUT /update/{id}` |
| Concentraciones | `/farma/productos/productos/catalogos/concentraciones` |
| Asociaciones de principios activos | `/farma/productos/productos/activos` |
| Compras, borradores, pagos, documentos y Kardex | `/farma/compras` |
| Catálogos de compras | `/farma/compras/catalogos` |
| Lotes, distribución y transferencias | `/farma/lotes`, `/farma/distribucion`, `/farma/transferencias` |
| Tipos de transferencia | `/farma/transferencias/tipos`; listar, crear, buscar, actualizar y eliminar |
| Clientes POS | `/pos/clientepos`; listar, crear, buscar, actualizar y eliminar |

Los servicios aceptan errores de validación tanto en `errors` como en `data.errors`. Las relaciones de productos se envían como objetos con `id`. El formulario selecciona laboratorios mediante una tabla que carga una página al abrirse y consulta `/filter_laboratorios` al buscar por nombre; al editar, el nombre actual viene en los datos del producto. Los otros cinco catálogos del formulario se solicitan completos con `all=1` (una petición por catálogo), conservando la lectura por páginas como compatibilidad con una API anterior. La API admite `all` en los seis endpoints de catálogos de productos; los consumidores que no lo envían conservan su paginación. La última imagen consultada se reutiliza durante cinco minutos en la sesión, separada por API y token, y se invalida al actualizar o eliminar el producto. El guardado de principios activos se valida y sincroniza en la API. La impresión admite el contrato plano actual del ticket y el contrato anidado anterior.

## Verificación

Tipos de transferencia se administra en Farma > Tipos de transferencia (`/farma/transferencias/tipos`). La API usa la tabla existente `transferencias_tipo`, con descripción obligatoria, única y de hasta 255 caracteres. La búsqueda se aplica antes de paginar. No se puede eliminar un tipo relacionado con transferencias. El modelo publica las altas, cambios y eliminaciones a HQ mediante el outbox existente; los envíos fallidos se reintentan con `hq:push` y el catálogo se distribuye a todos los stocks.

La migración del backend `2026_10_07_000003_register_transfer_type_permissions.php` registra la vista `tft` en Farma (`frm`) y las acciones `tftbtn01` (listar), `tftbtn02` (crear), `tftbtn03` (buscar), `tftbtn04` (actualizar) y `tftbtn05` (eliminar). Para otras instalaciones, aplica esa migración. Asigna las acciones desde Configuración > Usuarios y vuelve a iniciar sesión. La edición requiere buscar y actualizar; la API verifica la acción específica de cada ruta, el usuario, la vigencia y el estado activo del módulo y la vista. `SHADMIN_TRANSFER_TYPE_PATH` permite cambiar el prefijo de la API. Las pruebas de envío a HQ usan respuestas simuladas.

Clientes POS se administra en `/pos/clientepos`, dentro del módulo POS. La API usa la tabla independiente `clientepos` con nombre obligatorio (180 caracteres), RTN y teléfono opcionales (20) y dirección opcional (255). El RTN se guarda como texto para conservar ceros iniciales, sin espacios ni guiones; cuando se informa, debe ser único. Los nombres pueden repetirse. La búsqueda por nombre, RTN, teléfono o dirección se aplica en la API antes de paginar (30 registros por página).

Las migraciones del backend `2026_10_01_000022_create_clientepos_table.php` y `2026_10_01_000023_register_clientepos_permissions.php` crean la tabla y registran el módulo `pos`, la vista `clp` (`clientepos`) y las acciones `clpbtn01` (listar), `clpbtn02` (crear), `clpbtn03` (buscar), `clpbtn04` (actualizar) y `clpbtn05` (eliminar). Asigna las acciones necesarias desde Configuración > Usuarios y vuelve a iniciar sesión para actualizar el menú. La edición requiere buscar y actualizar. La API verifica la acción de cada ruta, el usuario, la vigencia y el estado activo del módulo y la vista. `SHADMIN_CLIENTEPOS_PATH` permite cambiar el prefijo de la API.

El historial de transferencias se limita por defecto al stock de la sesión: las enviadas salen de ese stock y las recibidas tienen ese stock como destino. El selector consulta directamente un stock activo y requiere la acción `trfbtn07` (Farma > Transferencias > filtrar por stock), que se verifica tanto en el frontend como en la API. Conserva dirección, tipo, búsqueda y paginación. El envío y la recepción siguen usando el stock de la sesión. Las migraciones `2026_10_01_000020_add_transfer_device_filter_action.php` y `2026_10_01_000021_rename_transfer_filter_action_to_stock.php` del backend registran y nombran esta acción sin asignarla a usuarios. Después de asignar el permiso, inicia sesión nuevamente para actualizar los permisos del frontend.

```sh
php vendor/phpunit/phpunit/phpunit
php vendor/laravel/pint/builds/pint --test app config routes tests tools
php -d memory_limit=512M vendor/phpstan/phpstan/phpstan analyse --no-progress
npm run build
```

Las pruebas del frontend usan SQLite en memoria, respuestas HTTP simuladas y prohíben conexiones HTTP imprevistas. Cada petición simulada bajo `/api/` se compara con las rutas reales exportadas del backend. Para actualizar ese inventario después de modificar rutas:

```powershell
php tools/export-api-routes.php 'C:\Users\USER\Documents\lv11\apishadai' > tests/Fixtures/api-routes.json
```

El exportador lee las declaraciones de rutas sin arrancar la API ni acceder a su base de datos. La existencia de una ruta no garantiza su autorización ni la validez del cuerpo: esas condiciones también se verifican con pruebas de servicios, componentes y dominio del backend.

Para ejecutar las pruebas de la API sin tocar datos reales, desde su carpeta, establece explícitamente el entorno aislado:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit
```

Al seleccionar un producto en Compras, se consulta `GET /farma/compras/ultimos-costos` con `productos[]` (hasta 200 IDs) y el permiso de opciones `cmpbtn07`. Devuelve `producto_id` y `costo` (decimal de dos posiciones o `null` sin historial). Se toma el costo unitario positivo de la compra registrada más reciente por ID, con ID del detalle como desempate; se excluyen borradores y, al editar, la compra indicada en `excluir_compra`. Abrir una compra consulta todos sus productos en un lote y conserva los costos guardados. El formulario reutiliza el resultado durante su edición y muestra la diferencia al cambiar el costo, sin repetir consultas ni mezclar ISV o descuentos. La migración de la API `2026_10_07_000006_index_purchase_detail_latest_cost.php` agrega el índice de cobertura `(producto, compra, id, costo)`, que permite resolver cada producto con una búsqueda ordenada y `LIMIT 1`, sin cargar todo el historial. Debe aplicarse también en otras instalaciones de la API.

La prueba manual final requiere una sucursal registrada y una sesión válida de la API. Las pruebas simuladas no reemplazan esa comprobación de despliegue.

## Catálogos de operación POS

La pantalla `/pos/catalogos` está en POS > Catálogos de operación POS. Lista los catálogos centrales y permite crear, editar, activar y desactivar medios de pago por stock. Los demás tipos conservan su consulta; sus formularios se incorporan por separado. La identidad (stock y código del medio) de un registro existente se conserva.

Asigna permisos al usuario del portal desde Configuración > Usuarios > Permisos > POS > Catálogos de operación POS: listar (`copbtn01`), crear (`copbtn02`), buscar (`copbtn03`), actualizar (`copbtn04`) y desactivar (`copbtn05`). Buscar y actualizar son necesarios para editar. Crear requiere además Configuración > Stocks > listar (`stkbnt02`) para cargar y validar la selección del stock. Estos permisos son del portal ShAdmin; Usuarios POS > Permisos administra los permisos operativos de los cajeros en ShPOS. No se asignan permisos automáticamente. Cierra e inicia sesión después de cambiar las asignaciones.

Para abrir una caja, registra en el stock correspondiente un medio de pago activo con «Es efectivo» marcado. Código y descripción son explícitos; «Requiere referencia» se selecciona según el medio de pago. HQ distribuye el catálogo a la base compartida y entonces se puede volver a consultar la apertura en ShPOS. ShAdmin nunca modifica el espejo local. La migración central `2026_10_04_000002_register_pos_management_views.php` registra la vista y las cinco acciones, sin concederlas a usuarios.

Las escrituras envían `Idempotency-Key`; ante una respuesta incierta el componente conserva el ID y el contenido de la operación, bloquea operaciones diferentes y ofrece Reintentar. Desactivar conserva el historial. Las pruebas usan SQLite en memoria y respuestas HTTP simuladas.

## Reglas de descuentos POS

En `/pos/descuentos`, «Eliminar» borra permanentemente una regla activa o inactiva después de confirmar. La API publica la eliminación para todos los stocks. «Desactivar» conserva la regla para poder reactivarla. Ambas operaciones exigen la revisión actual y admiten reintentos con la solicitud original.

La migración de la API `2026_10_14_000003_register_pos_discount_delete_permission.php` registra `dcpbtn06` (Eliminar reglas de descuentos), sin asignarlo automáticamente. Asigna esta acción desde Configuración > Usuarios > Permisos > POS > Reglas de descuentos y vuelve a iniciar sesión. La ruta es `DELETE /pos/descuentos/eliminar/{id}`.

## Compras recibidas desde ShPOS

POS → Compras POS consulta las recepciones enviadas desde los puntos de venta. Incluye listado paginado, filtros por stock/proveedor/producto/fechas/resultado y documento con unidades de compra originales, cantidades base, lotes, costos e ISV. La recepción se realiza desde ShPOS; esta pantalla permite revisarla.

La API ofrece GET /pos/compras/listar, /filtrar, /buscar/{request_uuid} y /opciones/{stocks|suppliers|products}. La migración central 2026_10_11_000005_register_pos_purchase_review registra la vista cpp bajo POS y las acciones cppbtn01 (Listar), cppbtn02 (Filtrar) y cppbtn03 (Ver), sin asignarlas automáticamente. Para mostrar el menú, asigna Listar compras POS desde Configuración → Usuarios/Permisos y vuelve a iniciar sesión.
