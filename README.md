# Web Scraper API

Backend independiente del proyecto Expo, construido con Laravel 12, PHP 8.2+ y MySQL 8. No crea ni inicializa un repositorio Git. El esquema administrado por migraciones también está disponible en `database/schema.sql` para instalación manual.

## Instalación local

1. Instalar PHP 8.2 o superior con `pdo_mysql`, Composer y MySQL 8; Node.js no es necesario para el backend.
2. Desde `Backend`, instalar dependencias y preparar el entorno:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

3. Escoger una sola inicialización: ejecutar `database/schema.sql` en MySQL **o** crear la base `web_scraper` y ejecutar las migraciones. Revisar `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` en `.env`. No ejecutar `schema.sql` encima de tablas que ya creó Artisan.

Para la opción con migraciones:

```powershell
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000
```

El archivo `.env` contiene secretos locales y no debe copiarse al frontend.

## URL para Expo

Configura `EXPO_PUBLIC_API_URL` en `prueba_scraping/.env` antes de iniciar Expo. El fallback local usa `http://10.0.2.2:8001/api` en Android Emulator y `http://127.0.0.1:8001/api` en web/iOS Simulator. Un teléfono físico necesita la IP LAN del equipo, por ejemplo `http://192.168.1.187:8001/api`, ambos dispositivos en la misma Wi-Fi y el puerto permitido por el firewall de Windows. Arranca Laravel con `php artisan serve --host=0.0.0.0 --port=8001`. CORS para desarrollo se configura en `config/cors.php`; fija `CORS_ALLOWED_ORIGINS` a los orígenes web permitidos antes de publicar. Para producción configura HTTPS.

## Scheduler del servidor

Las tareas activas se consultan cada minuto y se ejecutan en Laravel, aunque la app esté cerrada. En desarrollo se puede probar con:

```powershell
php artisan schedule:work
```

En producción, el servidor debe invocar `php artisan schedule:run` cada minuto. En Windows, configura una tarea del Programador de tareas que ejecute ese comando cada minuto. Usa `CACHE_STORE=database` (y ejecuta las migraciones) para que los locks contra duplicados se compartan entre procesos. El scraping programado hace HTTP desde el servidor, admite selectores CSS vía Symfony DomCrawler y rechaza hosts locales/IP privadas; sitios con CAPTCHA, JavaScript obligatorio o protecciones anti-bot pueden fallar y se registran como ejecución fallida.

## API

Todas las respuestas son JSON. Las listas paginadas usan la forma Laravel `{ data: [...], current_page, last_page, ... }`; las operaciones individuales usan `{ data: {...} }`. Validación devuelve `422`, recursos inexistentes `404`, creación `201`. La API aún no tiene autenticación; el throttle básico no sustituye autenticación y autorización para un despliegue público.

Al iniciar la app, se intenta migrar una vez el contenido existente de SQLite a MySQL. Si el servidor está fuera de línea, no se marca el proceso como completado y se volverá a intentar en el siguiente inicio. Los endpoints `POST /api/data/import/{projects|fields|tasks|executions|history}` importan por lotes de hasta 250 y conservan IDs, sin sobrescribir filas existentes; `GET /api/data/export` genera un JSON completo. La app puede seguir exportando/importando archivos JSON desde el panel, pero esas operaciones actúan sobre el backend.

| Método | Ruta | Función |
|---|---|---|
| GET, POST | `/api/projects` | Listar / crear proyectos |
| GET, PUT, DELETE | `/api/projects/{id}` | Consultar, editar o archivar proyecto |
| GET, POST | `/api/projects/{id}/fields` | Listar / crear campos dinámicos |
| GET, PUT, PATCH, DELETE | `/api/fields/{id}` | Consultar, editar o archivar campo |
| GET, POST | `/api/projects/{id}/tasks` | Listar / crear tareas |
| GET | `/api/tasks` | Listar tareas |
| PUT, PATCH, DELETE | `/api/tasks/{id}` | Editar o desactivar tarea |
| PATCH | `/api/tasks/{id}/pause`, `/resume` | Pausar / reanudar; al reanudar calcula la siguiente fecha |
| PATCH | `/api/tasks/{id}/schedule` | Registrar última y próxima ejecución |
| GET, POST | `/api/projects/{id}/executions`, `/api/executions` | Listar ejecuciones / crear ejecución móvil |
| GET | `/api/executions/{id}` | Detalle y valores |
| PATCH, POST | `/api/executions/{id}/complete`, `/values` | Completar ejecución y capturar valores |
| GET | `/api/executions/{id}/values` | Valores capturados en una ejecución |
| GET | `/api/projects/{id}/history` | Historial paginado, todos los tipos de campo |
| GET | `/api/projects/{id}/history/{executionId}` | Historial de una ejecución |
| GET | `/api/projects/{id}/prices`, `/prices/statistics` | Precios válidos y estadísticas |
| GET | `/api/fields/{id}/prices`, `/prices/statistics` | Serie y métricas de precio de un campo |
| GET | `/api/executions/statistics` | Estadísticas de ejecuciones |

Ejemplo para guardar campos y tareas:

```json
POST /api/projects
{
  "id": "proj_123",
  "name": "Producto",
  "url": "https://shop.example/item/1",
  "description": "Seguimiento de precio"
}

POST /api/projects/proj_123/fields
{
  "id": "field_123",
  "name": "Precio",
  "selector_css": ".price",
  "extraction_method": "visible_text",
  "data_type": "price",
  "is_active": true
}

POST /api/projects/proj_123/tasks
{
  "id": "task_123",
  "name": "Cada 210 minutos",
  "interval_value": 210,
  "interval_unit": "minutes"
}
```

## Pruebas

```powershell
php artisan test
php artisan route:list --path=api
```

Las pruebas de API usan SQLite en memoria; las operaciones reales usan MySQL definido en `.env`.<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
