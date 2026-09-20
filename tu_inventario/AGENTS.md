# AGENTS.md — Control de inventario (PHP + MySQL + PEPS)

Guía rápida para agentes (opencode u otros) que trabajen en este proyecto.

## Estado actual (sept 2026)

Sistema de inventario PHP/sin framework (`config.php` central), MySQL, sesiones
propias, sin Composer. Lógica de inventario **PEPS por lotes** (tabla `lotes` +
`consumo_lotes`).

Convención de codificación: PHP con `<?php`, sin comentarios salvo bloques
seccionales `// ============ NOMBRE ============`, indentación 4 espacios,
mensajes de UI en español, fechas en `d/m/Y`.

## Credenciales / entorno

- BD: `inventario_db` en `localhost`, user `root`, pass `123123` (config.php:6-10).
- Hay que levantar MySQL antes de probar (el agente NO debe asumir BD corriendo).
- Validación de sintaxis: `php -l archivo.php`.
- Para probar contra BD, arrancar MySQL y phMyAdmin por separado.

## Arquitectura de stock (IMPORTANTE)

- **Única fuente de verdad del stock = tabla `lotes`** (suma de `cantidad_disponible`).
- `getStock()` y `getStockActual()` delegan a `getStockLotes($producto_id)` (config.php:186).
- `registrarEntradaPEPS()` crea un lote por ENTRADA (config.php:94).
- `consumirLotesPEPS()` consume lotes FIFO por fecha con `FOR UPDATE` (config.php:115).
- Costo real de una salida = `costo_peps` (columna en `movimientos`). Para costos
  históricos sin `costo_peps` (CONSUMOs antiguos) usar `COALESCE(costo_peps, total)`.
- `getCostoConsumo()` ya usa ese `COALESCE` (config.php:282).

## Reglas de negocio ya corregidas (no revertir)

1. `AJUSTE` y `TRANSFERENCIA` NO deben registrarse desde `movimientos.php`
   (tira `Exception`). Los ajustes reales de stock se hacen SOLO desde
   `inventario_fisico.php` (ENTRADA/SALIDA + lotes). El formulario de
   movimientos solo ofrece ENTRADA | SALIDA | CONSUMO.
2. CONSUMO: al consumir lotes, el movimiento se actualiza con
   `costo_peps`, `total = costo_peps`, `precio_unitario = costo/cantidad`,
   `ganancia = 0` (no genera venta ni ganancia).
3. Inventario físico: cada conteo con diferencia se ajusta UNA sola vez.
   Helper `conteoFueAjustado($pdo, $conteo)` (config.php:323) detecta ajustes por
   comentarios: `Ajuste manual por inventario físico ID: N` o
   `conteo: X, sistema: Y`. La tabla muestra badge "✔ Ajustado" cuando ya se ajustó.
4. SALIDA es la única que genera ganancia (`total_venta - costo_peps`).

## Scripts one-off (NO tocar salvo pedido explícito)

- `migrar_lotes.php` — migra stock a lotes (no re-procesa CONSUMOs).
- `corregir_ajustes.php` — utilidad corregir ajustes, **hardcodeada al producto 19**.
- `verificar_peps.php`, `test_final.php` — diagnósticos; sin riesgos.

## Última sesión de trabajo (13 sept 2026)

Corrección que los lotes PEPS funcionen en movimientos. Archivos tocados, de más
reciente a más antiguo:

1. `movimientos.php` — ENTRADA crea lote (`registrarEntradaPEPS`), SALIDA/CONSUMO
   consumen lotes (`consumirLotesPEPS`) y actualizan `costo_peps`/`ganancia`.
2. `reportes.php` — costos/ganancias leyendo `costo_peps`.
3. `index.php` — KPIs con `costo_peps` y `COALESCE(costo_peps, total)`.
4. `inventario_fisico.php` — ajustes de stock vía lotes PEPS.
5. `config.php` — funciones PEPS, `getStockLotes`, `getCostoConsumo`, `conteoFueAjustado`.

NOTA: antes de la corrección, `movimientos.php` NO tocaba `lotes` ni `consumo_lotes`
(solo insertaba en `movimientos`), por lo que los lotes y el stock real quedaban
desfasados y no se aplicaba costeo FIFO.

## Mitigación para "memoria"

Este archivo se lee al iniciar cada sesión. Si cambia una regla de negocio,
actualizar esta sección para que las futuras sesiones partan informadas.

## Probado

- `php -l *.php` sin errores en todos los archivos.
- Falta validar contra BD real en producción sin errores del usuario.