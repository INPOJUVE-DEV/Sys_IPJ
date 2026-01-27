# Propuesta de Arquitectura: Módulo de Programas e Inscripciones (Sys IPJ)

**Fecha:** 22 de Enero, 2026  
**Contexto:** Integración de nuevos programas ("Cabina de Grabación", "Clases de Guitarra", "Club de Tareas") reutilizando el padrón de beneficiarios existente.

---

## 1. Diseño de Base de Datos (Schema)

El objetivo es tratar a los "Beneficiarios" como una entidad central de identidad, independientemente de si poseen la "Tarjeta Joven" o si son usuarios recurrentes de programas mensuales.

### A. Modificaciones a Tablas Existentes

#### Tabla `beneficiarios`
* **Cambio Crítico:** Convertir la columna `folio_tarjeta` a `NULLABLE`.
    * *Justificación:* Actualmente es obligatoria y única. Este cambio permite registrar personas que asisten a programas (como clases) sin obligarlas a tramitar la tarjeta física.
* **Identidad Única:** La validación de unicidad recaerá principalmente en la `curp` (que ya es `unique` en el esquema actual).

### B. Nuevas Tablas

#### 1. Tabla `programas` (Catálogo)
Define los servicios disponibles para inscripción.

| Columna | Tipo | Descripción |
| :--- | :--- | :--- |
| `id` | `BIGINT (PK)` | Identificador interno. |
| `nombre` | `VARCHAR` | Ej: "Clases de Guitarra", "Cabina de Grabación". |
| `slug` | `VARCHAR` | Ej: `clases-guitarra` (para rutas amigables). |
| `tipo_periodo` | `VARCHAR` | Ej: `mensual`, `unico`, `anual`. Controla la lógica de renovación. |
| `activo` | `BOOLEAN` | Para deshabilitar programas antiguos sin borrar historial. |

#### 2. Tabla `inscripciones` (Transaccional)
Vincula a una persona con un programa en un periodo específico.

| Columna | Tipo | Descripción |
| :--- | :--- | :--- |
| `id` | `UUID (PK)` | Identificador único de la inscripción. |
| `beneficiario_id` | `UUID (FK)` | Relación con tabla `beneficiarios`. |
| `programa_id` | `BIGINT (FK)` | Relación con tabla `programas`. |
| `periodo` | `VARCHAR(7)` | Ej: `2026-02`. Clave para el control de mensualidades y KPIs. |
| `estatus` | `VARCHAR` | Ej: `inscrito`, `baja`, `lista_espera`. |
| `fecha_renovacion`| `TIMESTAMP` | Opcional, para auditoría de re-inscripciones. |
| `created_by` | `UUID (FK)` | Relación con `users` (quién realizó la captura). |
| `created_at` | `TIMESTAMP` | Fecha de registro. |

---

## 2. Modelo de Dominio (Eloquent Relationships)

Definición de las relaciones en los modelos de Laravel para facilitar consultas.

### `App\Models\Beneficiario`
```php
public function inscripciones()
{
    // Un beneficiario tiene un historial de inscripciones (activas o pasadas)
    return $this->hasMany(Inscripcion::class);
}