# Troubleshooting

## Contenedores no inician

- Revisa `docker compose ps` y logs: `docker compose logs -f app nginx`
- Libera el puerto 80 si está en uso o cambia el mapeo en `docker-compose.yml`

## 502 Bad Gateway

- Valida que el contenedor `app` esté corriendo (PHP-FPM) y accesible en `app:9000`
- Revisa `.docker/nginx/default.conf` y errores en `/var/log/nginx/error.log`

## Error 500 en Laravel

- Ver logs en `sys_beneficiarios/storage/logs/laravel.log`
- Asegura `APP_KEY` configurada (`php artisan key:generate`), y caches limpias (`php artisan config:clear`)

## Conexión a MySQL rechazada

- Confirma `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD` en `.env` apuntando al servidor MySQL externo.
- Valida que el servidor permita conexiones desde la m�quina que ejecuta Docker (firewall/VPC).
- Limpia configuraci�n cacheada si cambias credenciales: `docker compose exec app php artisan config:clear`.

## Assets sin compilar

- Ejecuta `docker compose exec node npm install && npm run build`
- Si usas modo dev: `npm run dev` y verifica que `@vite` esté presente en layouts

## Importación de catálogos falla

- Verifica que existan `municipios.csv` y/o `secciones.csv` en `database/seeders/data/`
- Usa `--fresh` si necesitas limpiar tablas: `php artisan catalogos:import --fresh`
- Revisa formato de columnas y delimitador (coma o punto y coma)

