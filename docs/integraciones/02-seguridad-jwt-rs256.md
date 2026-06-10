# Seguridad de integración con JWT RS256

Este documento define el estándar de seguridad para integraciones sistema-a-sistema entre Sys_IPJ, API_TJ y otros sistemas autorizados.

## Objetivo

Evitar integraciones con tokens estáticos compartidos o credenciales reutilizadas.

Cada sistema integrador debe autenticarse con identidad propia, scopes específicos y tokens de vida corta.

## Principio

La integración debe ser backend a backend.

No se permite:

- firmar tokens desde frontend;
- guardar llaves privadas en navegador o app móvil;
- compartir una misma llave privada entre varios sistemas;
- usar tokens permanentes sin expiración;
- exponer endpoints de integración sin auditoría.

## Algoritmo recomendado

```txt
RS256
```

Modelo:

- El sistema emisor conserva su llave privada.
- El sistema receptor registra la llave pública del emisor.
- Los tokens se firman con la llave privada.
- Los tokens se validan con la llave pública.

## Clientes de integración

Cada sistema debe tener identidad propia.

Clientes previstos:

| Cliente | Descripción |
| --- | --- |
| `sys_ipj` | Sistema institucional fuente de verdad. |
| `api_tj` | API del canal digital Tarjeta Joven. |
| `unidad_informatica` | Sistema externo de consulta y envío de expedientes. |

No se deben mezclar credenciales entre clientes.

## Claims obligatorios

Todo JWT de integración debe incluir:

```json
{
  "iss": "sys_ipj",
  "sub": "sys_ipj",
  "aud": "api_tj",
  "scope": "cardholders.sync",
  "jti": "uuid-unico-por-request",
  "iat": 1713960000,
  "exp": 1713960300
}
```

| Claim | Requerido | Descripción |
| --- | --- | --- |
| `iss` | Sí | Cliente emisor. |
| `sub` | Sí | Sujeto del token; normalmente igual a `iss`. |
| `aud` | Sí | Audiencia esperada por el receptor. |
| `scope` | Sí | Permiso solicitado. |
| `jti` | Sí | ID único del token para anti-replay. |
| `iat` | Sí | Fecha de emisión. |
| `exp` | Sí | Fecha de expiración. |

## Header obligatorio

```json
{
  "alg": "RS256",
  "typ": "JWT",
  "kid": "sys_ipj-current"
}
```

| Campo | Requerido | Descripción |
| --- | --- | --- |
| `alg` | Sí | Debe ser `RS256`. |
| `typ` | Sí | Debe ser `JWT`. |
| `kid` | Sí | Identificador de la llave pública registrada. |

## Scopes mínimos

### Sys_IPJ hacia API_TJ

```txt
cardholders.sync
```

Uso:

- Enviar padrón mínimo sincronizado desde Sys_IPJ hacia API_TJ.

### API_TJ hacia Sys_IPJ

```txt
beneficiarios.staging.push
```

Uso:

- Enviar expediente staging aprobado desde API_TJ hacia Sys_IPJ.

### Unidad de Informática hacia API_TJ

```txt
cardholders.lookup
beneficiarios.staging.create
```

Uso:

- Consultar si una CURP existe en el padrón sincronizado.
- Crear expediente temporal cuando no exista.

## Audiencias esperadas

| Receptor | `aud` esperado |
| --- | --- |
| API_TJ | `api_tj` |
| Sys_IPJ | `sys_ipj` |

El receptor debe rechazar tokens cuyo `aud` no coincida exactamente.

## Expiración

Los tokens de integración deben tener vida corta.

Recomendación:

```txt
1 a 5 minutos
```

Tokens expirados deben responder:

```txt
401 Unauthorized
```

## Anti-replay con `jti`

Cada request debe usar un `jti` único.

Reglas:

- El receptor registra cada `jti` utilizado hasta que expire el token.
- Si llega un `jti` repetido, se rechaza la solicitud.
- El rechazo debe auditarse.

Respuesta sugerida:

```txt
401 Unauthorized
```

## Validación por middleware

El middleware de integración debe ejecutar, en este orden:

1. Validar header `Authorization: Bearer`.
2. Leer `kid` del JWT.
3. Resolver llave pública activa del cliente.
4. Validar firma RS256.
5. Validar `exp`, `iat`, `aud`, `iss`, `sub`.
6. Validar `jti` no reutilizado.
7. Resolver cliente de integración.
8. Validar IP allowlist si aplica.
9. Validar `scope`.
10. Registrar auditoría.
11. Entregar request al controlador.

## Códigos de respuesta

| Código | Caso |
| --- | --- |
| `401` | Token ausente, inválido, expirado, firma inválida, `kid` desconocido o `jti` repetido. |
| `403` | Token válido, pero cliente sin scope suficiente. |
| `422` | Payload mal formado o inválido. |
| `429` | Rate limit excedido. |
| `500` | Error interno controlado. |

## Rate limit

Debe existir rate limit por cliente.

Ejemplo inicial:

```txt
30 requests por minuto por cliente
```

Para operaciones pesadas como sync masivo, usar límites específicos o procesamiento asíncrono.

## IP allowlist

Si el ambiente lo permite, cada cliente puede tener lista de IP permitidas.

Reglas:

- Si la lista está vacía, no se valida IP.
- Si la lista tiene valores, se rechaza cualquier IP fuera de lista.
- Los rechazos deben auditarse.

## Auditoría

Cada request de integración debe registrar:

- cliente (`iss`);
- scope;
- endpoint;
- método HTTP;
- `jti`;
- IP origen;
- resultado;
- código HTTP;
- timestamp;
- error si aplica.

No se deben guardar llaves privadas ni tokens completos en logs.

## Manejo de llaves

Cada cliente debe tener:

- llave privada en su backend;
- llave pública registrada en el receptor;
- `kid` activo;
- mecanismo de rotación.

## Rotación de llaves

Modelo recomendado:

1. Registrar nueva llave pública con nuevo `kid`.
2. Permitir temporalmente llave anterior y nueva.
3. Cambiar emisor para firmar con nuevo `kid`.
4. Confirmar tráfico estable.
5. Revocar llave anterior.

## Variables de entorno sugeridas

### Sys_IPJ como receptor

```env
SYS_IPJ_INTEGRATION_AUDIENCE=sys_ipj
API_TJ_JWT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
API_TJ_JWT_KID=api_tj-current
API_TJ_ALLOWED_SCOPES=["beneficiarios.staging.push"]
```

### Sys_IPJ como emisor

```env
API_TJ_BASE_URL=https://api-tj.example.com
API_TJ_INTEGRATION_AUDIENCE=api_tj
SYS_IPJ_JWT_PRIVATE_KEY_PATH=storage/app/keys/sys_ipj_private.pem
SYS_IPJ_JWT_KID=sys_ipj-current
SYS_IPJ_ALLOWED_SCOPE=cardholders.sync
```

## Prohibiciones

No usar:

- tokens hardcodeados;
- secrets compartidos entre sistemas;
- llaves privadas dentro del repositorio;
- llaves privadas en frontend;
- JWT sin expiración;
- endpoints de integración sin scope;
- endpoints de integración sin auditoría.

## Relación con modelo de datos

La seguridad de integración no justifica modificar `beneficiarios`.

Cualquier dato de auditoría, `jti`, estado de request, resultado HTTP o error debe vivir en tablas separadas de integración.

## Resumen

Cada sistema debe tener identidad propia, scopes mínimos y trazabilidad completa.

La integración segura es una capa alrededor del modelo core, no una modificación del padrón oficial.
