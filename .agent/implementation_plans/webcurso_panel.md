# Plan de Implementación: Panel WebCurso

## Resumen
Migración del panel administrativo legacy (PHP 7.4) a Laravel con Livewire para la gestión de empresas y grupos de formación FUNDAE.

## Stack Tecnológico
- **Backend**: Laravel 11+
- **Frontend**: Livewire 3 + Blade
- **Auth**: Laravel Breeze (existente) + Spatie Permission
- **DB**: MySQL (tablas separadas por año)
- **Email**: Laravel Mail

## Estructura de Archivos a Crear

```
app/
├── Models/
│   ├── Empresa.php
│   ├── EmpresaAnterior.php
│   ├── Grupo.php
│   └── GrupoAnterior.php
├── Livewire/
│   └── Webcurso/
│       ├── Dashboard.php
│       ├── EmpresasIndex.php
│       ├── EmpresasTable.php
│       ├── GruposIndex.php
│       ├── GruposTable.php
│       ├── EmpresasSinGrupos.php
│       ├── ImportarCsv.php
│       └── EnviarSaldo.php
├── Services/
│   └── Webcurso/
│       ├── CsvImportService.php
│       └── EmpresaService.php
├── Mail/
│   └── SaldoEmpresaMail.php
database/
├── migrations/
│   ├── XXXX_create_empresas_table.php
│   ├── XXXX_create_empresas_anterior_table.php
│   ├── XXXX_create_grupos_table.php
│   └── XXXX_create_grupos_anterior_table.php
resources/
├── views/
│   └── livewire/
│       └── webcurso/
│           ├── dashboard.blade.php
│           ├── empresas-index.blade.php
│           ├── empresas-table.blade.php
│           ├── grupos-index.blade.php
│           ├── grupos-table.blade.php
│           ├── empresas-sin-grupos.blade.php
│           ├── importar-csv.blade.php
│           └── enviar-saldo.blade.php
│   └── emails/
│       └── saldo-empresa.blade.php
routes/
└── webcurso.php
```

## Fases de Implementación

### Fase 1: Base de Datos ✅ COMPLETADA
- [x] Crear migración `empresas`
- [x] Crear migración `empresas_anterior`
- [x] Crear migración `grupos`
- [x] Crear migración `grupos_anterior`
- [x] Ejecutar migraciones

### Fase 2: Modelos Eloquent ✅ COMPLETADA
- [x] Modelo `Empresa` con relaciones
- [x] Modelo `EmpresaAnterior`
- [x] Modelo `Grupo` con relaciones
- [x] Modelo `GrupoAnterior`

### Fase 3: Rutas y Middleware ✅ COMPLETADA
- [x] Crear archivo de rutas `webcurso.php`
- [x] Configurar middleware `role:admin`
- [x] Registrar rutas en `bootstrap/app.php`

### Fase 4: Componentes Livewire ✅ COMPLETADA
- [x] Dashboard principal con estadísticas
- [x] Tabla de empresas con filtros/paginación
- [x] Tabla de grupos con filtros
- [x] Vista empresas sin grupos
- [x] Componente importar CSV
- [x] Modal enviar saldo por email

### Fase 5: Servicios ✅ COMPLETADA
- [x] `CsvImportService` para procesar archivos

### Fase 6: Emails ✅ COMPLETADA
- [x] Mailable `SaldoEmpresaMail`
- [x] Vista email HTML

### Fase 7: Roles y Permisos ✅ COMPLETADA
- [x] Seeder `WebcursoRolesSeeder` para crear rol admin
- [x] Permisos para módulo webcurso

### Fase 8: Testing
- [ ] Tests de importación CSV
- [ ] Tests de envío de email
- [ ] Tests de filtros

## Acceso al Panel
- **URL Base**: `/webcurso`
- **Protección**: Middleware `auth` + `role:admin`
- **Menú**: Integrar en sidebar/navbar existente

## Notas Importantes
- Mantener compatibilidad con formato CSV original (separador `;`)
- Preservar lógica de UPSERT por CIF
- Email a: administracion@webcurso.es, webcurso@webcurso.es
