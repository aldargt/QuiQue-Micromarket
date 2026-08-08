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

La aplicación no permite registro público y no contiene credenciales predeterminadas.

## Administrador inicial

Después de migrar y ejecutar las semillas, crear el único administrador inicial con:

```powershell
C:\xampp\php\php.exe artisan micromarket:create-admin
```

El comando solicita nombre, correo y contraseña. La contraseña se captura de forma
oculta, se confirma y se almacena con el hashing configurado por Laravel. El comando
se niega a crear un segundo administrador.

La gestión de cajeros queda disponible en `Usuarios` después de iniciar sesión como
administrador.

## Decisiones de fundación

- Los roles son datos (`roles`) y no un paquete de permisos: por ahora solo existen
  `administrator` y `cashier`.
- El middleware `role` autoriza en backend y acepta uno o más slugs permitidos.
- Las cuentas inactivas no pueden autenticarse y una sesión existente se invalida.
- Las entidades operativas incluyen sucursal desde el inicio, aunque la interfaz
  trabajará inicialmente con una sola.
- Ventas y sus relaciones usan borrado restringido para proteger el historial.
- Cantidades usan tres decimales para contemplar productos por peso; dinero usa dos.
- La gestión de usuarios solo acepta campos explícitos y asigna el rol Cajero en el
  backend; un valor de rol manipulado en una petición se ignora.
- Clientes y participaciones de sorteos no forman parte de estas etapas. La venta
  mantiene un identificador estable para relacionarlos posteriormente y el futuro
  umbral configurable se modelará como configuración administrativa, sin acoplarlo
  a los importes ni a la tabla de usuarios actuales.

## Categorías

Administradores y cajeros activos pueden listar, buscar, crear, editar, activar y
desactivar categorías de su sucursal desde `Categorías`. No existe eliminación
física. Los nombres se limpian de espacios repetidos y no pueden duplicarse dentro
de una misma sucursal. La clave foránea existente en `products.category_id` queda
preparada para la Etapa 4, sin implementar todavía el módulo de productos.
