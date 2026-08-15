# Scheduler de backups en Windows

Laravel ya evalúa cada hora si corresponde ejecutar el backup automático. Para que
esa evaluación ocurra aunque nadie inicie sesión, cree una tarea en el Programador
de tareas de Windows con estos valores:

- Programa: `C:\xampp\php\php.exe`
- Argumentos: `artisan schedule:run`
- Iniciar en: la ruta absoluta del proyecto (por ejemplo,
  `C:\Users\HP\Desktop\QuiQue-Micromarket`)
- Desencadenador: cada minuto, indefinidamente.

La tarea de Windows solo inicia el scheduler. Laravel mantiene la regla de siete
días, evita ejecuciones superpuestas y controla los reintentos configurados.

Las rutas del ejemplo deben adaptarse si PHP o el proyecto se trasladan. La cuenta
que ejecute la tarea necesita permiso de lectura/escritura sobre la carpeta privada
configurada en `BACKUP_LOCAL_PATH`.
