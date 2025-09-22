# Roles y accesos

## Admin

- Acceso a dashboard: `/admin` y KPIs en `/admin/kpis`
- Gestión de usuarios: `/admin/usuarios`
- Gestión de beneficiarios (listado/detalle/export): `/admin/beneficiarios`
- Importación de catálogos: `/admin/catalogos`

## Encargado 360

- Acceso a dashboard: `/s360/enc360` y KPIs en `/s360/enc360/dash`
- Asignaciones y gestión de sesiones según permisos S360
- Navbar simplificada: solo muestra "Dashboard" y "Captura" para facilitar el uso.
- Alcance puede limitarse por municipios asignados (ver `User::municipiosAsignados`)

## Capturista

- Panel personal: `/capturista`
- KPIs personales: `/capturista/kpis` (alias: `/mi-progreso/kpis`)
- Mis registros (CRUD limitado): `/mis-registros`

## Recursos comunes

- CRUD `beneficiarios` y `domicilios` (según rol)
- Perfil de usuario: `/profile`
