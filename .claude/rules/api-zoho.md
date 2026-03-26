# API Zoho CRM — Referencia

> TODO: Este archivo se completara cuando se desarrolle la integracion con Zoho CRM.

## Integracion prevista

- Sincronizar candidatos del Panel con contactos/deals en Zoho CRM
- Evitar doble entrada de datos (actualmente se registran manualmente en ambos sistemas)
- Posible sincronizacion bidireccional: CRM → Panel (nuevos clientes) y Panel → CRM (estado de requisitos)

## Informacion necesaria para implementar

- Credenciales de API Zoho (OAuth2)
- Mapeo de campos: Candidato del Panel ↔ Contacto/Deal en Zoho CRM
- Definir direccion de sincronizacion y frecuencia
- Definir que eventos disparan la sincronizacion
