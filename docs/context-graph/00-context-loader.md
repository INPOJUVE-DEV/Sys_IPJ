# Sys_IPJ — Codex Context Loader

Paquete de contexto estructurado para que Codex cargue solo lo necesario por tarea.

Base confirmada por README:
- Laravel 11
- Blade + Bootstrap 5 + Vite
- Docker: PHP-FPM + Nginx + Node
- MySQL externo
- Laravel Breeze
- Spatie Permission
- Spatie Activitylog
- aplicación en `sys_beneficiarios/`

## Uso por tarea

### Beneficiarios / domicilios
Cargar:
- `01-runtime-map.yaml`
- `02-route-surface.yaml`
- `05-domain-entities.yaml`
- `07-security-invariants.md`
- rutas, controladores, modelos y migraciones de beneficiarios/domicilios

### Roles / usuarios
Cargar:
- `03-auth-permissions.yaml`
- `07-security-invariants.md`
- rutas, controladores y modelos de usuarios
- seeders de roles/permisos

### Catálogos municipales / secciones
Cargar:
- `04-catalogs-map.yaml`
- `05-domain-entities.yaml`
- importadores, seeders y comandos relacionados

### Integración con API_TJ
Cargar:
- `06-data-flow-api-tj.yaml`
- `05-domain-entities.yaml`
- `07-security-invariants.md`

### Docker / deploy
Cargar:
- `01-runtime-map.yaml`
- `07-security-invariants.md`
- `docker-compose.yml`
- `.docker/nginx/default.conf`
- `sys_beneficiarios/.env.example`
