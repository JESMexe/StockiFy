# 📦 StockiFy — Sistema de Gestión de Inventarios
![Static Badge](https://img.shields.io/badge/Status%20-EN%20DESARROLLO-orange)
![Static Badge](https://img.shields.io/badge/Backend-PHP-blue)
![Static Badge](https://img.shields.io/badge/Database-MySQL-green)
![Static Badge](https://img.shields.io/badge/Release%20date-2026-yellow)

Bienvenido a **StockiFy**, un sistema web de **gestión de inventarios y operaciones comerciales** que desarrollé como proyecto personal para practicar desarrollo **full-stack** y diseño de aplicaciones reales.

La idea del proyecto es ofrecer una plataforma desde la cual un negocio pueda administrar su **stock, ventas, compras, clientes y proveedores** desde un solo lugar.

Este proyecto forma parte de mi **portfolio como programador junior**, y lo sigo mejorando constantemente mientras aprendo nuevas prácticas y mejoro el código.

---

# 🎯 ¿Qué hace StockiFy?

StockiFy permite gestionar inventarios de forma centralizada.

Actualmente incluye funcionalidades como:

* 📦 Gestión de productos
* 📊 Control de stock
* 💰 Registro de ventas
* 🧾 Registro de compras
* 👥 Gestión de clientes
* 🚚 Gestión de proveedores
* 🧑‍💼 Gestión de empleados
* 💳 Métodos de pago
* 🔔 Sistema de notificaciones
* 🗂️ Sistema multi-inventario por usuario

El objetivo es simular un sistema que podría usar un **negocio real** para administrar su flujo comercial diario.

---

# 👤 ¿Quién soy?

Soy **JESM**, programador junior enfocado en desarrollo **full-stack y diseño visual**.

Me gusta construir proyectos que no solo funcionen, sino que también tengan una **interfaz clara y una arquitectura entendible**.

StockiFy es uno de los proyectos más grandes que desarrollé hasta ahora y me permitió practicar:

* diseño de bases de datos relacionales
* arquitectura de aplicaciones web
* integración de servicios externos
* organización de proyectos grandes
* desarrollo de interfaces funcionales

---

# 🛠️ Tecnologías utilizadas

Backend

* PHP
* MySQL / MariaDB
* Composer

Frontend

* HTML
* CSS
* JavaScript

Herramientas de desarrollo

* PHPMailer (envío de correos)
* Google OAuth (inicio de sesión con Google)
* HeidiSQL (administración de base de datos)

---

# 🧱 Estructura del proyecto

```
StockiFy/
│
├─ public/ → punto de entrada del sistema
├─ src/ → lógica principal de la aplicación
├─ database/
│  └─ schema.sql → estructura completa de la base de datos
│
├─ .env.example → ejemplo de configuración
├─ composer.json → dependencias del proyecto
└─ README.md
```

---

# 🚀 Cómo ejecutarlo localmente

## Requisitos

Para correr el proyecto necesitás:

* PHP 8 o superior
* MySQL o MariaDB
* Composer
* Un entorno local como:

    * XAMPP
    * Laragon
    * WAMP

---

## Instalación

### 1️⃣ Clonar el repositorio

```
git clone https://github.com/JESMexe/StockiFy.git
cd StockiFy
```

---

### 2️⃣ Crear la base de datos

Crear una base nueva llamada:

```
uproject_db
```

Luego importar el archivo:

```
database/esquema.sql
```

Esto va a generar toda la estructura necesaria para el sistema.

---

### 3️⃣ Configurar variables de entorno

Copiar el archivo:

```
.env.example
```

y renombrarlo a:

```
.env
```

Luego completar los datos de conexión a la base.

Ejemplo:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uproject_db
DB_USERNAME=root
DB_PASSWORD=1234
```

---

### 4️⃣ Instalar dependencias

```
composer install
```

---

### 5️⃣ Iniciar el servidor

Iniciar Apache y MySQL y abrir en el navegador:

```
http://localhost/StockiFy/public
```

---

# 🧪 Primer uso

Cuando se ejecuta por primera vez:

1. Crear un usuario
2. Crear un inventario
3. Empezar a cargar productos, clientes y proveedores

La base de datos se crea vacía a propósito para simular el **primer uso real del sistema**.

---

# 📚 ¿Por qué este proyecto?

Quise crear StockiFy porque me interesaba desarrollar un sistema más complejo que una simple aplicación CRUD.

Este proyecto me permitió trabajar con:

* múltiples entidades relacionadas
* arquitectura modular
* autenticación de usuarios
* manejo de inventarios
* lógica de negocio más cercana a una aplicación real

Además, lo uso como **laboratorio personal** para probar mejoras y encontrar bugs.

---

# ⚠️ Estado del proyecto

StockiFy **sigue en desarrollo**.

Todavía estoy trabajando en:

* optimización de consultas SQL
* mejoras en la vista responsive

---

# 🤝 Contribuciones

Por el momento **no se aceptan contribuciones externas**.

Este proyecto es personal y lo utilizo principalmente como parte de mi proceso de aprendizaje y portfolio.

---

# 📜 Licencia

Este proyecto se publica únicamente con fines de demostración técnica dentro de mi portfolio.

El código fuente no puede ser utilizado, modificado ni redistribuido sin autorización explícita del autor.

Para consultas comerciales o licencias de uso:
jesmdeveloper@gmail.com
