# Tickets System

Sistema ligero de venta y gestión de tickets para eventos (retiros, campamentos, conciertos, etc.).

## Características

- 🎫 Catálogo de eventos con diseño moderno
- 💳 Sistema de compra seguro
- 📱 Generación de tickets con códigos QR
- 📧 Envío automático de tickets por email
- 📱 Compartir tickets por WhatsApp
- 🔐 Panel de administración protegido
- 📊 Estadísticas y reportes
- 🎨 Diseño responsive con Tailwind CSS

## Tecnologías

- **Backend**: PHP 7.4+
- **Base de datos**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript Vanilla
- **Framework CSS**: Tailwind CSS
- **Librerías**: PHPMailer, TCPDF, PHP QR Code

## Requisitos del Servidor

- PHP 7.4 o superior
- MySQL 5.7 o superior
- Extensiones PHP: PDO, MBString, GD, cURL
- Composer para gestión de dependencias
- Servidor web (Apache/Nginx)

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/tu-usuario/tickets.git
cd tickets
```

### 2. Instalar dependencias

```bash
composer install
```

### 3. Configurar base de datos

1. Crear una base de datos MySQL
2. Importar el archivo `database.sql`
3. Configurar las credenciales en `includes/config/config.php`

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tickets_system');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
```

### 4. Configurar correo electrónico

Edita `includes/config/config.php` con tus credenciales SMTP:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'tu-email@gmail.com');
define('SMTP_PASSWORD', 'tu-app-password');
```

### 5. Configurar permisos

```bash
chmod 755 public/uploads public/qrcodes
chmod 644 includes/config/config.php
```

### 6. Configurar servidor web

#### Apache (htaccess)

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ public/index.php [QSA,L]
```

#### Nginx

```nginx
location / {
    try_files $uri $uri/ /public/index.php?$query_string;
}
```

## Estructura de Directorios

```
tickets/
├── admin/                  # Panel de administración
│   ├── dashboard.php      # Dashboard principal
│   ├── events.php         # Gestión de eventos
│   ├── tickets.php        # Gestión de tickets
│   ├── login.php          # Login de admin
│   └── logout.php         # Cerrar sesión
├── public/                # Archivos públicos
│   ├── index.php          # Landing page
│   ├── buy.php            # Proceso de compra
│   ├── success.php        # Confirmación de compra
│   ├── ticket.php         # Vista individual de ticket
│   ├── uploads/           # Archivos subidos
│   └── qrcodes/           # Códigos QR generados
├── includes/              # Archivos del sistema
│   ├── config/            # Configuración
│   ├── classes/           # Clases PHP
│   └── functions/         # Funciones auxiliares
├── assets/                # Recursos estáticos
│   ├── css/
│   ├── js/
│   └── images/
├── vendor/                # Dependencias Composer
├── database.sql           # Estructura de base de datos
├── composer.json          # Configuración Composer
└── .gitignore            # Archivos ignorados por Git
```

## Configuración Inicial

### Acceso al Panel de Administración

- URL: `http://tu-dominio.com/admin/`
- Usuario por defecto: `admin`
- Contraseña por defecto: `admin123`

**Importante**: Cambia la contraseña del administrador después del primer acceso.

### Crear Eventos

1. Ingresa al panel de administración
2. Ve a "Eventos"
3. Haz clic en "Nuevo Evento"
4. Completa la información del evento
5. Sube una imagen (opcional)
6. Guarda el evento

### Proceso de Compra

1. Los usuarios visitan la landing page
2. Seleccionan un evento y hacen clic en "Comprar"
3. Completan el formulario con sus datos
4. El sistema genera códigos QR únicos
5. Envía los tickets por email automáticamente
6. Redirige a una página de confirmación

## Variables de Configuración

Edita `includes/config/config.php` para personalizar:

```php
// URL del sitio
define('SITE_URL', 'https://tu-dominio.com');

// Configuración de correo
define('SMTP_HOST', 'tu-smtp-server');
define('SMTP_USERNAME', 'tu-email');
define('SMTP_PASSWORD', 'tu-password');

// Rutas del sistema
define('ROOT_PATH', __DIR__);
define('UPLOADS_PATH', ROOT_PATH . '/public/uploads');
```

## Seguridad

- Las credenciales de base de datos están protegidas por `.gitignore`
- El panel de administración requiere autenticación
- Los inputs son sanitizados contra XSS
- Las consultas SQL usan prepared statements
- Los archivos subidos son validados

## Mantenimiento

### Respaldar Base de Datos

```bash
mysqldump -u usuario -p tickets_system > backup_$(date +%Y%m%d).sql
```

### Limpiar Archivos Temporales

```bash
find public/uploads -name "*.tmp" -delete
find public/qrcodes -name "*.png" -mtime +30 -delete
```

### Actualizar Dependencias

```bash
composer update
```

## Personalización

### Colores del Tema

Los colores principales están definidos en CSS:

```css
:root {
    --color-gray-light: #d9d9d9;
    --color-gray-dark: #363c40;
    --color-gray-medium: #babebf;
    --color-gray-muted: #848b8c;
    --color-black: #202426;
}
```

### Email Templates

Los templates de email están en la función `generateEmailBody()` en `includes/functions/functions.php`.

## Troubleshooting

### Problemas Comunes

1. **Error 500**: Verifica los permisos de las carpetas `uploads` y `qrcodes`
2. **Email no se envía**: Configura correctamente las credenciales SMTP
3. **QR no se genera**: Asegúrate que la extensión GD esté habilitada
4. **Base de datos no conecta**: Verifica credenciales en `config.php`

### Logs de Error

Activa el modo debug en `config.php`:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Contribuir

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit tus cambios (`git commit -am 'Agrega nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Abre un Pull Request

## Licencia

MIT License - puedes usar este proyecto para fines comerciales y personales.

## Soporte

Para soporte técnico o preguntas, abre un issue en el repositorio de GitHub.

---

**Desarrollado con ❤️ para la gestión de eventos**
