# CLASSIC_SOCKS - Docker setup

Este proyecto incluye un `Dockerfile` y `docker-compose.yml` para ejecutar una aplicación PHP (puro) con MySQL.

Servicios:
- app: PHP 8.0 + Apache
- db: MySQL 8.0



Instrucciones rápidas:

1. Construir y levantar los contenedores:

```bash
docker-compose up --build -d
```

2. Acceder a la aplicación en http://localhost:8080

3. Conectarse a la base de datos desde el host (opcional):

```bash
mysql -h 127.0.0.1 -P 3306 -u classicuser -p
```

4. Parar y eliminar contenedores/volúmenes:

```bash
docker-compose down -v
```

5. Importar esquema inicial (una vez que el contenedor de MySQL esté listo):

```bash
# Importa el esquema SQL dentro del contenedor de la base de datos
# Asegúrate de que los contenedores estén arriba (ver paso 1)
# Reemplaza 'rootpass' con la contraseña configurada en docker-compose.yml
docker-compose exec -T db mysql -u root -p classicdb < sql/schema.sql
```

6. Panel de administración:

Abre http://localhost:8080/admin.php

Notas:
- Si tu aplicación requiere otras extensiones PHP, agrégalas en el `Dockerfile` usando `docker-php-ext-install`.
- El volumen `./:/var/www/html` monta todo el proyecto en el contenedor para desarrollo. Para producción, considera copiar solo los archivos necesarios sin montar volúmenes.
