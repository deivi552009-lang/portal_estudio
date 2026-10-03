# Portal Estudio

Portal académico web para la gestión de grupos de estudio: docentes gestionan grupos, estudiantes, calificaciones, asistencias y actividades, mientras los estudiantes consultan sus grupos, el estado de las clases y las actividades de sus materias.

La aplicación es un sistema web (sin API REST por el momento) construida sobre **Laravel 12** y **Livewire 4.3.5**, con base de datos **PostgreSQL** (despliegue de referencia en **Supabase**, schema `portal_estudio`).

> Nombre de referencia del proyecto: **Portal  Estudio** — ver `docs/README.md`.

## Características principales

- **Autenticación y roles** (Breeze + middleware `role`): registro, login, recuperación de contraseña, verificación de email y control de acceso por rol (`Docente`, `Estudiante`, `Administrativo`, `Superadministrador`).
- **Panel docente**:
  - Dashboard con sus grupos y totales (estudiantes, talleres).
  - Gestión de grupos (crear, editar, eliminar) ligada al período académico activo, con evaluaciones oficiales fijas `P1`–`P4` y `A`.
  - Búsqueda, alta y baja de estudiantes en cada grupo.
  - Matriz de calificaciones con evaluaciones dinámicas y notas 0–5.
  - Registro de asistencia por fecha (presente/ausente/excusa/tarde) e historial semestral con nota final ponderada.
  - Actividades (talleres, tareas, guías) con carga de archivos y seguimiento de vencimientos.
- **Panel estudiante**: sus grupos, estado de cada clase (próxima/en curso/finalizada con progreso) y listado de actividades de sus materias.

## Requisitos previos

| Dependencia | Versión mínima | Notas |
|---|---|---|
| **PHP** | 8.2 | Con extenciones estándar de Laravel (ver `composer.json`: `php ^8.2`) |
| **Composer** | 2.x | Para las dependencias de PHP |
| **Laravel** | 12 | Ya incluido en el repositorio (`laravel/framework ^12.0`); no se instala globalmente |
| **Node.js** | 20.19+ (recomendado 20 LTS o 22 LTS) | Para Vite 7 y Tailwind |
| **Base de datos** | PostgreSQL | El proyecto usa **PostgreSQL** (en desarrollo: instancia Supabase, schema `portal_estudio`). `.env.example` trae SQLite por defecto; hay que cambiarlo a `pgsql` |
| **Extensiones PHP recomendadas** | `pdo_pgsql` | Necesaria para conectar a PostgreSQL |

## Instalación y despliegue local

### 1. Clonar el repositorio

```bash
git clone <url-del-repositorio> portal_estudio
cd portal_estudio
```

### 2. Instalar dependencias de PHP

```bash
composer install
```

### 3. Configurar el entorno (`.env`)

```bash
copy .env.example .env        # Windows
cp .env.example .env         # Linux/macOS
```

Genera la clave de la aplicación:

```bash
php artisan key:generate
```

Edita `.env` y configura la conexión a PostgreSQL (los valores de Supabase se usan en el proyecto actual):

```dotenv
DB_CONNECTION=pgsql
DB_HOST=aws-0-us-east-2.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=tu_usuario_supabase
DB_PASSWORD=tu_password
DB_SCHEMA=portal_estudio
```

> Para una base de datos local, usa `DB_HOST=127.0.0.1`, `DB_PORT=5432` y crea un schema `portal_estudio` en tu PostgreSQL. Las sesiones, el cache y la cola usan el driver `database` (`SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`), por lo que las tablas auxiliares se crean con las migraciones.

### 4. Crear el esquema de base de datos y los roles

```bash
php artisan migrate --force
php artisan db:seed          # Siembra los 4 roles: Superadministrador, Administrativo, Docente, Estudiante
```

### 5. Instalar y compilar los assets

```bash
npm install
npm run build
```

### 6. Enlazar el almacenamiento público

Los archivos de las actividades se guardan en el disco `public` (carpeta `actividades/`):

```bash
php artisan storage:link
```

### 7. Iniciar la aplicación

```bash
php artisan serve
```

Abre <http://localhost:8000> en el navegador.

### Alternativa: script de setup en un solo comando

`composer.json` define el script `setup`, que ejecuta lo anterior (installer, `.env`, clave, migraciones, npm y build):

```bash
composer setup
```

Después deberías configurar la base de datos en `.env`, ejecutar `php artisan db:seed` y `php artisan storage:link`.

## Desarrollo

Scripts disponibles en `composer.json`:

| Comando | Descripción |
|---|---|
| `composer dev` | Levanta en paralelo: `php artisan serve`, `queue:listen`, `pail` (logs) y `vite dev` |
| `npm run dev` | Solo el watcher de Vite |
| `composer test` | Limpia cachés y ejecuta `php artisan test` (PHPUnit) |
| `npm run build` | Compila los assets para producción |

## Estructura de referencia

- `app/Http/` — controladores (Breeze + perfil), middleware `role` y FormRequests.
- `app/Livewire/Docente` y `app/Livewire/Estudiante` — componentes de las páginas de negocio.
- `app/Services/` — lógica de negocio (asistencias, calificaciones, estudiantes de grupos).
- `app/Models/` — modelos Eloquent.
- `routes/web.php` y `routes/auth.php` — rutas de negocio y autenticación.
- `resources/views/` — vistas Blade (layouts, componentes, paneles).

## Documentación

La documentación del proyecto vive en la carpeta `docs/`:

| Documento | Contenido |
|---|---|
| [docs/README.md](docs/README.md) | Presentación general del sistema (nombre, tecnologías, roles, estado) |
| [docs/arquitectura.md](docs/arquitectura.md) | Patrones de diseño, organización de controladores, middlewares, rutas y frontend; estructura de carpetas y decisiones de diseño |
| [docs/base_datos.md](docs/base_datos.md) | Modelo de base de datos: tablas, columnas y tipos, claves/índices, relaciones Eloquent e historial de migraciones |
| [docs/historias_usuario.md](docs/historias_usuario.md) | Historias de usuario por épicas, con criterios de aceptación |
| [docs/roadmap.md](docs/roadmap.md) | Estado de las funcionalidades (completadas, en progreso) y plan de mejora/seguridad |

## Licencia

Proyecto de desarrollo interno (Portal Estudio). El framework base, Laravel, es software de código abierto bajo la licencia MIT.
