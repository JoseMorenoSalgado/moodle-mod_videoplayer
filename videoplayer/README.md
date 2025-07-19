# video-drive-moodle
# 📹 Video Elearning Cloud – Plugin para Moodle

Este plugin permite incrustar y reproducir videos almacenados en **Google Drive** directamente en el aula virtual de Moodle como una **actividad de tipo video**.

---

## ✅ Requisitos

- Moodle **4.1** o superior.
- Acceso de administrador para instalar complementos.
- Un video subido a Google Drive con permiso de acceso general.

---

## 📦 Instalación del Plugin

1. Descarga el archivo `mod_videoplayer.zip` (si aún no lo tienes).
2. Ve al panel de administración de Moodle.
3. Dirígete a:  
   `Administración del sitio → Plugins → Instalar plugins → Subir un archivo`.
4. Sube el archivo `.zip` y continúa el proceso de instalación.
5. Moodle detectará automáticamente el nuevo módulo de actividad llamado **Video Elearning Cloud**.
6. Verifica que el plugin quede correctamente instalado.

---

## 🧩 Estructura del Plugin

- El plugin se instala como un nuevo tipo de actividad.
- Se muestra con un ícono personalizado azul.
- Compatible con el selector de actividades del theme Edwiser.

---

## 🎥 Cómo incrustar un video desde Google Drive

1. **Sube el video** a tu cuenta de Google Drive.
2. Haz clic derecho sobre el video y selecciona **Compartir**.
3. En la parte de “Acceso general”, selecciona:  
   **"Cualquier persona con el vínculo" → Lector**
4. Haz clic en **Copiar vínculo**.
5. El enlace debe tener esta forma:


6. Pega ese enlace completo en el campo **"Enlace de Google Drive"** al crear la actividad en Moodle.

---

## 🔒 Seguridad y privacidad

- El plugin reproduce el video usando `iframe` con restricciones (`sandbox`) para evitar que los usuarios puedan abrir Google Drive directamente.
- El botón de pantalla completa está habilitado (si el navegador lo permite).
- Solo personas con el enlace del video podrán verlo, según la configuración de Google Drive.

---

## 🧑‍🏫 ¿Quién puede usarlo?

- Cualquier docente con permisos para agregar actividades puede usar el plugin.
- El estudiante verá el video directamente sin salir de Moodle.

---

## 📌 Soporte

- Moodle 4.1 o superior
- Compatible con temas como Edwiser y Boost

---

## 👨‍💻 Autor

**Jose Erasmo Moreno Elearning Cloud**  
https://elearningcloud.io  
