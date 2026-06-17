# Smoke local Sys_IPJ ↔ API_TJ

## Objetivo

Preparar un ambiente local o staging para ejecutar pruebas end-to-end reales sin usar llaves ni secretos productivos.

## 1. Variables minimas en Sys_IPJ

Verificar en `.env` local o variables del contenedor:

```env
API_TJ_BASE_URL=http://host.docker.internal:8080
API_TJ_AUDIENCE=api_tj
SYS_IPJ_INTEGRATION_AUDIENCE=sys_ipj
SYS_IPJ_JWT_ISSUER=sys_ipj
SYS_IPJ_JWT_SUBJECT=sys_ipj
SYS_IPJ_JWT_KID=sys_ipj-current
SYS_IPJ_PRIVATE_KEY_PATH=storage/app/keys/sys_ipj_private.pem
SYS_IPJ_SCOPE=cardholders.sync
CURP_HASH_SECRET=<secreto-local-compartido-con-API_TJ>
API_TJ_INTEGRATION_USER_EMAIL=integracion.api_tj@inpojuve.local
INTEGRATION_PAYLOAD_ENCRYPTION_KEY=base64:<32-bytes-locales>
```

Notas:

- `API_TJ_AUDIENCE` debe ser `api_tj`.
- `INTEGRATION_PAYLOAD_ENCRYPTION_KEY` es obligatoria para auditoria inbound cifrada.
- `SYS_IPJ_PRIVATE_KEY_PATH` debe apuntar a un archivo no versionado y legible dentro de la app.

## 2. Generar llave local de Sys_IPJ

Desde `sys_beneficiarios/`:

```bash
mkdir -p storage/app/keys
openssl genpkey -algorithm RSA -out storage/app/keys/sys_ipj_private.pem -pkeyopt rsa_keygen_bits:2048
openssl rsa -pubout -in storage/app/keys/sys_ipj_private.pem -out storage/app/keys/sys_ipj_public.pem
```

No versionar:

- `storage/app/keys/sys_ipj_private.pem`
- `storage/app/keys/sys_ipj_public.pem`

## 3. Exportar la publica de Sys_IPJ a API_TJ

Configurar en `API_TJ` la llave publica generada por `Sys_IPJ`:

```env
SYS_IPJ_JWT_PUBLIC_KEY=-----BEGIN PUBLIC KEY-----...
SYS_IPJ_JWT_KID=sys_ipj-current
SYS_IPJ_ALLOWED_SCOPES=["cardholders.sync"]
```

## 4. Generar o ubicar la publica de API_TJ

La llave publica de `API_TJ` debe existir en un archivo local no versionado, por ejemplo:

```txt
sys_beneficiarios/storage/app/keys/api_tj_public.pem
```

## 5. Cargar la publica de API_TJ en Sys_IPJ

Con el cliente `api_tj` ya sembrado:

```bash
docker compose run --rm app php artisan integrations:keys:upsert api_tj api-tj-current /var/www/html/storage/app/keys/api_tj_public.pem
```

Validar en DB:

```sql
SELECT c.client_code, k.kid, k.status
FROM integration_client_keys k
JOIN integration_clients c ON c.id = k.client_id
WHERE c.client_code = 'api_tj';
```

## 6. Definir llave de cifrado inbound

Ejemplo local:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Copiar el valor a:

```env
INTEGRATION_PAYLOAD_ENCRYPTION_KEY=base64:...
```

## 7. Validar prerequisitos locales

```bash
docker compose run --rm app php vendor/bin/pest
docker compose run --rm app php artisan migrate:fresh --seed
docker compose run --rm app sh -lc "php artisan route:list | grep integrations"
docker compose run --rm app php artisan integrations:keys:upsert api_tj api-tj-current /var/www/html/storage/app/keys/api_tj_public.pem
```

Esperado:

- Suite verde.
- Migraciones OK.
- Solo existe `POST api/v1/integrations/api-tj/staging/accept`.
- `integration_client_keys` queda con una llave activa para `api_tj`.

## 8. Smoke inbound

Sin token:

```bash
curl -i -X POST http://localhost/api/v1/integrations/api-tj/staging/accept \
  -H 'Content-Type: application/json' \
  -d '{}'
```

Esperado:

```txt
HTTP 401
status = unauthorized
```

Con token valido firmado por `API_TJ`:

- `iss = api_tj`
- `aud = sys_ipj`
- `scope = beneficiarios.staging.push`
- `kid = api-tj-current`

Payload minimo:

```json
{
  "external_request_id": "API-TJ-LOCAL-0001",
  "source": "api_tj",
  "submitted_by": {
    "system": "api_tj",
    "user_id": "local-admin",
    "name": "Administrador local API_TJ"
  },
  "beneficiario": {
    "folio_tarjeta": "TJ-LOCAL-0001",
    "nombre": "LAURA",
    "apellido_paterno": "MARTINEZ",
    "apellido_materno": "SOTO",
    "curp": "MOCJ050521MSPNRL01",
    "fecha_nacimiento": "2005-05-21",
    "sexo": "F",
    "discapacidad": false,
    "id_ine": "INELOCAL123",
    "telefono": "4441234567",
    "domicilio": {
      "calle": "AV QA",
      "numero_ext": "100",
      "numero_int": null,
      "colonia": "CENTRO",
      "municipio_id": 1,
      "codigo_postal": "78000",
      "seccional": "0001"
    }
  }
}
```

## 9. Smoke outbound

Verificar:

- `API_TJ` escuchando en `http://127.0.0.1:8080`.
- Llave privada outbound legible en `SYS_IPJ_PRIVATE_KEY_PATH`.
- `CURP_HASH_SECRET` compartido entre ambos sistemas.

Luego disparar sync desde la UI admin o por servicio y validar:

- request con body `items`;
- cada item con `curp_hash`, `curp_masked`, `tarjeta_numero`, `status`;
- resultados por indice;
- `conflict`, `skipped`, `rejected` y `error` quedan auditados por item.

## 10. Criterio GO local/staging

GO si:

- existe llave privada local de `Sys_IPJ`;
- existe llave publica activa de `api_tj` en `integration_client_keys`;
- `INTEGRATION_PAYLOAD_ENCRYPTION_KEY` esta configurada;
- `API_TJ_AUDIENCE=api_tj`;
- inbound responde `401` sin token y `201` con JWT valido;
- outbound alcanza `API_TJ` y audita resultados por indice.
