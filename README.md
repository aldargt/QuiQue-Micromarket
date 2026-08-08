# Sistema - QuiQue Micromarket

Fundación técnica del POS e inventario construida con Laravel 12, PHP 8.2,
MySQL/MariaDB, Blade, Tailwind CSS y JavaScript.

## Puesta en marcha local

1. Iniciar Apache y MySQL desde el panel de XAMPP.
2. Crear la base `quique_micromarket` con cotejamiento `utf8mb4_unicode_ci`.
3. Copiar `.env.example` a `.env` si `.env` no existe y ajustar credenciales.
4. Ejecutar `C:\xampp\php\php.exe artisan key:generate`.
5. Ejecutar `C:\xampp\php\php.exe artisan migrate --seed`.
6. Copiar el contenido de `deploy/apache/quique-micromarket.conf.example` al final de
   `C:\xampp\apache\conf\extra\httpd-vhosts.conf` y reiniciar Apache.
7. Ejecutar `npm install` y `npm run build` cuando cambien los assets.
8. Abrir `http://localhost/quique-micromarket`.

La aplicación no permite registro público. La creación y administración de cuentas
se implementará en la Etapa 2. No hay credenciales predeterminadas en el repositorio.

## Decisiones de fundación

- Los roles son datos (`roles`) y no un paquete de permisos: por ahora solo existen
  `administrator` y `cashier`.
- El middleware `role` autoriza en backend y acepta uno o más slugs permitidos.
- Las cuentas inactivas no pueden autenticarse y una sesión existente se invalida.
- Las entidades operativas incluyen sucursal desde el inicio, aunque la interfaz
  trabajará inicialmente con una sola.
- Ventas y sus relaciones usan borrado restringido para proteger el historial.
- Cantidades usan tres decimales para contemplar productos por peso; dinero usa dos.
