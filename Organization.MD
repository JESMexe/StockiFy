gestion-stock-app/
│
├── public/                   # Carpeta pública (servida por Apache/Nginx)
│   ├── index.php              # Punto de entrada del backend
│   ├── assets/                # Archivos estáticos accesibles desde el navegador
│   │   ├── css/
│   │   ├── js/
│   │   └── img/
│   └── uploads/               # Subidas de usuarios (imágenes, documentos, etc.)
│
├── src/                       # Código fuente del backend PHP
│   ├── config/
│   │   ├── config.php         # Configuración global (DB, entorno, etc.)
│   │   └── routes.php         # Rutas de la aplicación (si usas router propio)
│   │
│   ├── controllers/           # Controladores (lógica de negocio)
│   │   ├── ProductController.php
│   │   ├── CategoryController.php
│   │   └── AuthController.php
│   │
│   ├── models/                # Modelos (acceso a la BD)
│   │   ├── Product.php
│   │   ├── Category.php
│   │   └── User.php
│   │
│   ├── views/                 # Vistas (HTML con variables PHP)
│   │   ├── layout/
│   │   │   ├── header.php
│   │   │   └── footer.php
│   │   ├── products/
│   │   │   ├── index.php
│   │   │   ├── edit.php
│   │   │   └── create.php
│   │   └── auth/
│   │       ├── login.php
│   │       └── register.php
│   │
│   ├── core/                  # Clases base reutilizables
│   │   ├── Database.php       # Conexión PDO con MariaDB
│   │   ├── Controller.php     # Clase base para controladores
│   │   └── Response.php       # Manejo de respuestas (JSON, HTML)
│   │
│   └── helpers/               # Funciones auxiliares generales
│       ├── auth.php
│       └── utils.php
│
├── tests/                     # pruebas unitarias
│
├── .env                       # Variables de entorno (credenciales DB, etc.)
├── composer.json              
├── README.md
└── .gitignore
