# Plan Completo de Cierre del Proyecto

Fecha de inicio del plan: 2026-04-16  
Proyecto: Tick System Onn (System_app)  
Horizonte sugerido: 6 a 8 semanas

## 1. Objetivo General

Completar el proyecto hasta estado de entrega final, cubriendo:

- estabilidad funcional
- calidad de codigo
- seguridad operativa
- observabilidad
- documentacion tecnica y operativa
- pipeline de entrega confiable

## 2. Definicion de Terminado (Definition of Done Global)

El proyecto se considera terminado cuando se cumplan todos los puntos:

1. Funcionalidad core cerrada y validada por pruebas.
2. Seguridad y autorizacion verificadas en escenarios criticos.
3. API documentada formalmente.
4. Monitoreo y trazabilidad operativa activos.
5. Pipeline de CI/CD con release reproducible.
6. Runbooks operativos y de contingencia disponibles.
7. Checklist final de release completado y aprobado.

## 3. Supuestos del Plan

- El alcance funcional principal (Tickets, Locations, Categories, QR, IA) se mantiene.
- No se agregan modulos grandes fuera del backlog priorizado.
- El equipo ejecuta por iteraciones semanales o quincenales.
- Se mantiene disciplina de pruebas en cada fase.

## 4. Roles Sugeridos

- Backend Lead: diseno tecnico, integridad de dominio, code review.
- Backend Dev: implementacion endpoints, servicios, jobs.
- QA: pruebas funcionales, regresion, evidencia de acceptance.
- DevOps: CI/CD, observabilidad, despliegue, rollback.
- Product/Owner academico: validacion funcional final.

## 5. Roadmap por Fases

## Fase 0 - Baseline y gobierno de cierre

Objetivo: congelar alcance de entrega final y alinear criterios de cierre.

Tareas:

- T0.1 Confirmar backlog de cierre y prioridades.
- T0.2 Definir version objetivo de entrega (release tag).
- T0.3 Publicar este plan y el estado actual como base oficial.

Actividades:

- Crear tablero de tareas con estados: pending, in_progress, done, blocked.
- Asignar responsables por tarea.
- Definir calendario semanal de seguimiento.

Entregables:

- Baseline aprobado.
- Cronograma de ejecucion.
- Matriz de responsables.

Criterio de aceptacion:

- Alcance final aprobado por todos los roles.
- No hay tareas criticas sin responsable.

Estimacion: S (1-2 dias)

---

## Fase 1 - Seguridad y observabilidad minima

Objetivo: eliminar puntos ciegos operativos y endurecer superficie de riesgo.

Tareas:

- T1.1 Implementar endpoint de salud (/health).
- T1.2 Definir logging estructurado para eventos criticos.
- T1.3 Integrar error tracking centralizado.
- T1.4 Revisar cabeceras y configuracion de seguridad HTTP.

Actividades:

- Agregar chequeos de DB, queue y storage en health.
- Estandarizar formato de logs por evento (ticket_created, qr_generation_failed, etc).
- Configurar canal de alertas para errores severos.
- Verificar throttle y middleware de seguridad en rutas sensibles.

Entregables:

- Health endpoint documentado.
- Estrategia de logs implementada.
- Error tracking activo en entorno objetivo.

Criterio de aceptacion:

- Health responde 200 en estado correcto y error en degradacion.
- Se pueden trazar fallos de jobs y errores de API en un panel central.

Verificacion sugerida:

- php artisan test
- Prueba manual de /health en estado normal y con fallo simulado

Estimacion: M (4-6 dias)

Dependencias: Fase 0 completada.

---

## Fase 2 - Comunicacion y notificaciones

Objetivo: cerrar el ciclo de comunicacion de eventos de ticket.

Tareas:

- T2.1 Implementar modulo de notificaciones (mail/log/notificacion interna).
- T2.2 Disparar notificaciones en eventos clave.
- T2.3 Definir reglas de retry y control de ruido.

Actividades:

- Crear plantillas para ticket creado, cambio de estado, duplicado detectado.
- Conectar listeners de dominio con canal de notificacion.
- Implementar fallback cuando el proveedor de correo falle.

Entregables:

- Notificaciones funcionales en eventos criticos.
- Evidencia de pruebas de envio y manejo de error.

Criterio de aceptacion:

- Evento critico produce salida de notificacion verificable.
- Fallo de proveedor no rompe flujo de negocio principal.

Verificacion sugerida:

- Feature tests del flujo de notificaciones
- Validacion manual con entorno local/staging

Estimacion: M (4-6 dias)

Dependencias: Fase 1.

---

## Fase 3 - Robustez de dominio y reglas de negocio

Objetivo: endurecer reglas para evitar estados invalidos y degradacion de integridad.

Tareas:

- T3.1 Formalizar validaciones de transicion de estados de ticket.
- T3.2 Revisar edge cases de roles en cambios de estado.
- T3.3 Asegurar consistencia de auditoria en operaciones sensibles.

Actividades:

- Revisar matriz completa de transiciones permitidas.
- Agregar pruebas de transiciones invalidas.
- Verificar que toda mutacion relevante deje rastro auditable.

Entregables:

- Matriz de transiciones validada.
- Reglas de dominio cubiertas por pruebas.
- Auditoria consistente en cambios criticos.

Criterio de aceptacion:

- Transiciones invalidas retornan error controlado.
- No hay mutacion critica sin registro de auditoria.

Verificacion sugerida:

- php artisan test --filter=TicketApiControllerTest
- php artisan test

Estimacion: M (3-5 dias)

Dependencias: Fase 2.

---

## Fase 4 - Calidad de pruebas y cobertura

Objetivo: elevar confianza tecnica antes de release final.

Tareas:

- T4.1 Aumentar pruebas unitarias en servicios core.
- T4.2 Agregar pruebas de integracion de flujos E2E.
- T4.3 Cerrar huecos de politicas y validaciones.

Actividades:

- Unit tests para TicketCreationService, TicketStateService, QrImageService.
- Pruebas de jobs con escenarios success/fail.
- Pruebas de autorizacion por rol en endpoints sensibles.

Entregables:

- Nuevas suites unitarias y de integracion.
- Reporte de cobertura mejorada.

Criterio de aceptacion:

- Suite completa en verde.
- Cobertura de areas criticas incrementada de forma medible.

Verificacion sugerida:

- php artisan test
- Reporte de cobertura (si se habilita)

Estimacion: L (6-10 dias)

Dependencias: Fase 3.

---

## Fase 5 - CI/CD, release y operacion

Objetivo: pasar de CI basico a entrega controlada por ambientes.

Tareas:

- T5.1 Extender pipeline (lint + tests + build + release).
- T5.2 Definir estrategia de despliegue por ambiente.
- T5.3 Implementar rollback tecnico y procedimiento operativo.

Actividades:

- Agregar jobs de calidad y artefactos.
- Definir variables seguras por ambiente.
- Ejecutar simulacro de rollback.

Entregables:

- Pipeline reproducible de punta a punta.
- Manual corto de despliegue y rollback.

Criterio de aceptacion:

- Un release puede ejecutarse con pasos documentados y repetibles.
- Existe plan de reversa probado.

Verificacion sugerida:

- Ejecucion de pipeline en rama release.
- Evidencia de deploy en ambiente objetivo.

Estimacion: M (4-7 dias)

Dependencias: Fase 4.

---

## Fase 6 - Documentacion tecnica y operativa final

Objetivo: dejar el proyecto mantenible sin conocimiento tacito.

Tareas:

- T6.1 Publicar documentacion formal de API.
- T6.2 Publicar runbooks operativos.
- T6.3 Actualizar README y CHANGELOG para entrega final.

Actividades:

- Describir endpoints, contratos, errores y ejemplos.
- Documentar troubleshooting de colas, QR y IA.
- Incluir checklists de pre-release y post-release.

Entregables:

- API docs publicadas.
- Runbooks de operacion y contingencia.
- README/CHANGELOG alineados al estado real.

Criterio de aceptacion:

- Un nuevo integrante puede levantar y operar el sistema solo con docs.

Verificacion sugerida:

- Revisión cruzada de docs por otro miembro del equipo.

Estimacion: M (4-6 dias)

Dependencias: Fase 5.

---

## Fase 7 - Go-live controlado y estabilizacion

Objetivo: cerrar formalmente proyecto con evidencia tecnica y operativa.

Tareas:

- T7.1 Ejecutar prueba integral de punta a punta.
- T7.2 Cerrar riesgos abiertos o documentar aceptacion de riesgo residual.
- T7.3 Crear acta de cierre tecnico.

Actividades:

- Smoke test de rutas core Web y API.
- Verificacion de jobs QR e IA en entorno objetivo.
- Validar dashboard de logs y alertas.

Entregables:

- Evidencia de go-live.
- Lista de riesgos residuales.
- Version final etiquetada.

Criterio de aceptacion:

- Checklist global de release completado al 100%.

Verificacion sugerida:

- Prueba de regresion final.
- Monitoreo de 24-48 horas post release.

Estimacion: S-M (2-4 dias)

Dependencias: Fase 6.

---

## 6. Backlog Priorizado (Resumen Ejecutivo)

## Prioridad P0 (bloqueante de cierre)

- P0-01 Health endpoint y chequeos operativos.
- P0-02 Observabilidad minima (logging + error tracking).
- P0-03 Documentacion API formal.
- P0-04 Notificaciones de eventos criticos.
- P0-05 Pruebas de robustez en reglas de estado y roles.

## Prioridad P1 (alta)

- P1-01 Cobertura unitaria de servicios core.
- P1-02 Pruebas de integracion de flujos completos.
- P1-03 Pipeline CI/CD de release + rollback.
- P1-04 Runbooks operativos.

## Prioridad P2 (media)

- P2-01 Mejoras no criticas de experiencia.
- P2-02 Automatizacion extra de reportes.
- P2-03 Optimizacion de performance avanzada.

## 7. Cronograma Sugerido (8 Semanas)

- Semana 1: Fase 0 + inicio Fase 1
- Semana 2: cierre Fase 1
- Semana 3: Fase 2
- Semana 4: Fase 3
- Semana 5-6: Fase 4
- Semana 7: Fase 5 y Fase 6
- Semana 8: Fase 7 + cierre final

Nota: si el equipo es pequeno, usar 6 semanas concentrando Fases 5 y 6 en paralelo.

## 8. Matriz de Trazabilidad (Brecha -> Fase)

| Brecha | Fase donde se resuelve |
|---|---|
| Falta de health check | Fase 1 |
| Observabilidad incompleta | Fase 1 |
| Notificaciones faltantes | Fase 2 |
| Endurecimiento de transiciones de estado | Fase 3 |
| Cobertura de pruebas parcial | Fase 4 |
| CI/CD incompleto para release | Fase 5 |
| Runbooks y documentacion operativa faltante | Fase 6 |
| Cierre formal de proyecto | Fase 7 |

## 9. Checklist de Ejecucion por Sprint

Usar esta lista en cada iteracion:

- [ ] Tareas del sprint definidas con owner y fecha.
- [ ] Casos de prueba definidos antes de merge.
- [ ] PR revisado y aprobado.
- [ ] Pruebas automatizadas en verde.
- [ ] Evidencia funcional adjunta.
- [ ] Documentacion actualizada.
- [ ] Riesgos y bloqueos registrados.

## 10. Checklist Final de Release

- [ ] Todas las fases completadas o justificadas.
- [ ] Sin bloqueantes P0 abiertos.
- [ ] Suite de pruebas completa en verde.
- [ ] API documentada y publicada.
- [ ] Monitoreo y alertas activos.
- [ ] Pipeline de release probado.
- [ ] Runbook de rollback validado.
- [ ] Version final etiquetada y changelog actualizado.

## 11. Primer Sprint Recomendado (Inicio inmediato)

Para empezar hoy mismo:

1. Crear endpoint /health con chequeos de DB y queue.
2. Definir formato de logs para eventos de ticket y QR.
3. Diseñar estructura base de API docs.
4. Crear tareas tecnicas para modulo de notificaciones.
5. Definir matriz de pruebas unitarias prioritarias.

Con esto, el proyecto entra en una ruta clara de cierre sin perder foco en calidad y operacion.
