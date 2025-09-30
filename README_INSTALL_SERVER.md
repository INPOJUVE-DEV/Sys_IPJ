# Guía de instalación en servidor Ubuntu

Este documento describe los requisitos y pasos para desplegar **Sys IPJ 2025** usando Docker en un servidor Ubuntu Server (20.04 LTS o 22.04 LTS). Está orientado a entornos sin entorno gráfico y con acceso mediante SSH.

## 1. Requisitos previos del servidor

- Ubuntu Server 20.04/22.04 actualizado.
- Usuario con privilegios `sudo`.
- Al menos 2 vCPU, 4 GB de RAM y 30 GB de almacenamiento libre (recomendado para bases de datos y builds).
- Acceso a Internet para descargar dependencias e imágenes de Docker.
- Puertos abiertos en el firewall para:
  - `80/tcp` (HTTP) y `443/tcp` (HTTPS) si vas a publicar el sitio.
  - Opcionalmente `3306/tcp` si expondrás MySQL fuera del host (no recomendado en producción).

## 2. Instalar dependencias base

```bash
sudo apt update
sudo apt install -y ca-certificates curl gnupg lsb-release git ufw
```

Configura la zona horaria y sincronización de reloj si es necesario:

```bash
sudo timedatectl set-timezone America/Mexico_City
sudo timedatectl set-ntp true
```

## 3. Instalar Docker Engine y Docker Compose plugin

1. Agrega la clave GPG oficial de Docker y el repositorio estable:

    ```bash
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
    ```

2. Instala Docker Engine, CLI, containerd y el plugin de Compose:

    ```bash
    sudo apt update
    sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    ```

3. Agrega tu usuario al grupo `docker` para evitar usar `sudo` en cada comando y aplica el cambio:

    ```bash
    sudo usermod -aG docker $USER
    newgrp docker
    ```

4. (Opcional) Habilita y verifica el servicio en el arranque:

    ```bash
    sudo systemctl enable docker
    sudo systemctl status docker
    ```

## 4. Clonar el repositorio de la aplicación

```bash
cd /opt # o la ruta deseada
sudo git clone https://github.com/tu-org/Sys_ipj_2025-web.git
sudo chown -R $USER:$USER Sys_ipj_2025-web
cd Sys_ipj_2025-web
```

## 5. Configurar variables de entorno de Laravel

1. Copia el archivo de ejemplo:

    ```bash
    cp sys_beneficiarios/.env.example sys_beneficiarios/.env
    ```

2. Edita `sys_beneficiarios/.env` con los valores apropiados para producción:

    ```dotenv
    APP_NAME="Sys IPJ 2025"
    APP_ENV=production
    APP_KEY= # se generará con artisan más adelante
    APP_DEBUG=false
    APP_URL=https://tu-dominio

    LOG_CHANNEL=stack
    LOG_LEVEL=info

    DB_CONNECTION=mysql
    DB_HOST=mysql
    DB_PORT=3306
    DB_DATABASE=sys_beneficiarios
    DB_USERNAME=root
    DB_PASSWORD=secret

    # Configuración de correo y storage según tu infraestructura
    MAIL_MAILER=smtp
    MAIL_HOST=smtp.tudominio
    MAIL_PORT=587
    MAIL_USERNAME=usuario
    MAIL_PASSWORD=contraseña
    MAIL_ENCRYPTION=tls
    MAIL_FROM_ADDRESS=no-reply@tudominio
    MAIL_FROM_NAME="Sys IPJ 2025"
    ```

3. Si vas a conectar con un motor MySQL administrado (RDS, Cloud SQL, etc.), cambia `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD` y elimina/ignora el servicio `mysql` del `docker-compose.yml`.

## 6. Ajustar docker-compose para producción (opcional)

- Modifica el mapeo de puertos del servicio `web` si necesitas escuchar en otro puerto (por ejemplo `8080:80`).
- Configura volúmenes persistentes en rutas locales personalizadas si deseas un control más estricto que los volúmenes nombrados.
- Revisa `.docker/nginx/default.conf` para añadir cabeceras de seguridad o configuraciones de proxy necesarias.

## 7. Construir e iniciar los contenedores

```bash
docker compose pull           # descarga imágenes actualizadas
docker compose up -d --build  # levanta app, web, mysql y node
```

Comprueba el estado:

```bash
docker compose ps
docker compose logs -f web
```

## 8. Inicializar la aplicación Laravel

```bash
docker compose exec app php artisan key:generate
# Migraciones y seeders base
docker compose exec app php artisan migrate --seed
# Instalar dependencias de Node y compilar assets
docker compose exec node npm install
docker compose exec node npm run build
# Optimizar caches
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

Si importas catálogos desde CSV colócalos en `sys_beneficiarios/database/seeders/data/` antes de ejecutar `migrate --seed` o bien usa `php artisan catalogos:import` tras el despliegue.

## 9. Configurar firewall y proxy reverso

1. Permite el tráfico HTTP/HTTPS con UFW (si está habilitado):

    ```bash
    sudo ufw allow OpenSSH
    sudo ufw allow "Nginx Full"
    sudo ufw enable
    sudo ufw status
    ```

2. Para certificados SSL en producción se recomienda usar un proxy reverso externo (por ejemplo Nginx o Traefik) en el host o frente a los contenedores. Ajusta el `server_name` y certificados en `.docker/nginx/default.conf` si decides manejar SSL dentro del mismo stack.

## 10. Tareas de mantenimiento

- **Respaldos de base de datos:** usa `docker compose exec mysql mysqldump ...` o configura tareas automáticas externas.
- **Actualizaciones:**
  - `git pull` para traer cambios del repositorio.
  - `docker compose pull` y `docker compose up -d --build` para reconstruir imágenes.
- **Logs:**
  - Laravel: `sys_beneficiarios/storage/logs/`.
  - Servicios Docker: `docker compose logs <servicio>`.
- **Monitoreo:** considera integrar herramientas como Uptime Kuma, Netdata o Prometheus para métricas.

---

Con estos pasos tendrás la aplicación corriendo en un servidor Ubuntu usando contenedores Docker, lista para integrarse detrás de un balanceador o proxy con SSL.
