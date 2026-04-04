# 📑 Contexto de Proyecto: cicd/Agent (The Deployment Bridge)

> **Origen**: Este proyecto nace de la necesidad crítica de estabilizar el ecosistema de **House-Co / Lumina**, operado bajo un entorno de hosting compartido en Neubox.

---

## 🚩 1. El Problema Raíz (RCA)
La infraestructura original de House-Co intentaba ejecutar motores de inteligencia artificial (Gemini) y procesamiento de PDFs (LlamaParse) directamente en un hosting compartido. 
- **Restricción**: El servidor final (Neubox) aplica límites estrictos de CPU/RAM y ha deshabilitado el uso de Phusion Passenger para aplicaciones Python.
- **Consecuencia**: Errores 500/403 constantes y procesos interrumpidos.

---

## 🏛️ 2. El Pivote Estratégico (Arquitectura Híbrida)
Se decidió desacoplar la plataforma mediante el patrón **"Hybrid Worker"**:
1. **Procesamiento**: Se realiza localmente en un Mac Studio (Cluster de alta potencia).
2. **Sincronización**: **Supabase Cloud** actúa como el puente de datos en tiempo real entre lo local y lo público.
3. **Presentación**: La Web en Neubox actúa únicamente como un "Gateway" ligero de lectura.

---

## 🛠️ 3. El Nacimiento de cicd/Agent (Épica 10)
Al notar el éxito del "Deployment Hook" automatizado para estabilizar House-Co, se decidió extraer la lógica de automatización CI/CD como un proyecto **Standalone**.

### Funcionalidad Core Identificada:
- **Disparador**: Webhooks de GitHub.
- **Orquestador**: n8n o script de Python en el ambiente local.
- **Transferencia**: Motor de análisis de diferencias (Diff-Engine) que minimiza el uso de banda ancha.
- **Hook Atómico**: Un script de entrada en PHP (`deploy_hook.php`) que recibe paquetes, los valida vía HMAC y actualiza el servidor de producción sin tiempo de inactividad (Zero Downtime).

---

## 📈 4. Estado Actual para el Relevo
- **Estructura**: El proyecto `cicd/Agent` está inicializado y vinculado a GitHub [hzapata82/cicd-Agent].
- **Documentación**: Existe un README básico y una jerarquía de carpetas profesional.
- **Siguiente Hito**: Generalizar la variable de entorno y los tokens de seguridad para que el motor sea agnóstico de House-Co y pueda usarse en cualquier proyecto cPanel/FTP.

---

*Documento generado por Agente Antigravity en sesión con Henry Zapata.*
*Fecha: 04 de Abril de 2026*

---

## 🛠️ 5. Inventario de Código a Extraer de House-Co
Para completar la migración a `cicd/Agent`, se deben mover los siguientes componentes desde el repositorio `houseco`:

1. **El Gateway PHP (Hook)**:
   - Ruta: `/Users/henryzapata/Projects/houseco/saas-engine/scripts/deploy_hook.php`
   - Función: Recibe el paquete comprimido desde el orquestador y lo descomprime atómicamente en el hosting compartido.

2. **Utilidades de Diagnóstico**:
   - Ruta: `/Users/henryzapata/Projects/houseco/saas-engine/scripts/test_path.php`
   - Función: Script para validar rutas absolutas y permisos de escritura en Neubox.

3. **Orquestador n8n (Export)**:
   - Los flujos de despliegue en n8n que conectan el Webhook de GitHub con el envío FTP deben exportarse como JSON y guardarse en la carpeta `/workflows/` de este repositorio.

4. **Scripts de Sincronización Local**:
   - Cualquier script de Python local que realice el "Diff Analysis" entre el repo y el destino debe ser generalizado para usar variables de entorno en lugar de rutas fijas.

---

*Documento actualizado: 04 de Abril de 2026*
