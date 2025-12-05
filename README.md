# SMB Web Client - PHP 8 Modern Edition

Modern PHP 8 rewrite of the classic SMB Web Client using FrankenPHP and the icewind/smb library.

## Features

- 🚀 **PHP 8.2+** with modern syntax (typed properties, match expressions, named arguments)
- 🔥 **FrankenPHP** powered by Go and Caddy for high performance
- 📦 **icewind/smb** library for native SMB/CIFS support (no more smbclient CLI)
- 🎨 **Clean architecture** with PSR-4 autoloading and dependency injection
- 🔒 **Secure authentication** with session management
- 🌍 **Multi-language support** (Spanish, English, French)
- 🐳 **Docker ready** with docker-compose

## Requirements

- PHP 8.2 or higher
- FrankenPHP (or any PHP web server)
- libsmbclient library
- Composer

## Installation

### Using Docker (Recommended)

1. Clone the repository:
```bash
git clone <repository-url>
cd smbwebclient
```

2. Copy the environment file:
```bash
cp .env.example .env
```

3. Edit `.env` with your SMB server configuration:
```env
SMB_DEFAULT_SERVER=your-smb-server
SMB_ROOT_PATH=
APP_DEFAULT_LANGUAGE=es
```

4. Build and run with Docker Compose:
```bash
docker-compose up -d
```

5. Access the application at http://localhost:8080

### Manual Installation

1. Install dependencies:
```bash
composer install
```

2. Configure your environment:
```bash
cp .env.example .env
# Edit .env with your settings
```

3. Run with FrankenPHP:
```bash
frankenphp run --config Caddyfile
```

Or use PHP's built-in server:
```bash
php -S localhost:8080 -t public
```

## Configuration

All configuration is managed through environment variables in the `.env` file:

### SMB Configuration
- `SMB_DEFAULT_SERVER`: Default SMB server (default: localhost)
- `SMB_ROOT_PATH`: Root path to restrict navigation
- `SMB_HIDE_DOT_FILES`: Hide files starting with dot (default: true)
- `SMB_HIDE_SYSTEM_SHARES`: Hide system shares like C$, ADMIN$ (default: true)
- `SMB_HIDE_PRINTER_SHARES`: Hide printer shares (default: false)

### Application Configuration
- `APP_DEFAULT_LANGUAGE`: Default language (es, en, fr)
- `APP_DEFAULT_CHARSET`: Character encoding (default: UTF-8)
- `APP_CACHE_PATH`: Path for cached files
- `APP_SESSION_NAME`: Custom session name
- `APP_ALLOW_ANONYMOUS`: Allow anonymous access (default: false)
- `APP_MOD_REWRITE`: Enable mod_rewrite URLs (default: false)
- `APP_BASE_URL`: Base URL for mod_rewrite

### Logging
- `LOG_LEVEL`: Log verbosity (0-3)
- `LOG_FACILITY`: Syslog facility

## Features Comparison

| Feature | Legacy Version | Modern Version |
|---------|---------------|----------------|
| PHP Version | PHP 4.1+ | PHP 8.2+ |
| SMB Access | smbclient CLI | icewind/smb library |
| Web Server | Apache/Nginx | FrankenPHP/Any |
| Architecture | Procedural | OOP with PSR-4 |
| Type Safety | None | Strict typing |
| Performance | Moderate | High (FrankenPHP) |
| Dependencies | None | Composer |

## Architecture

```
src/
├── Application.php     # Main application controller
├── Config.php         # Configuration management
├── SmbClient.php      # SMB operations wrapper
├── Session.php        # Session and authentication
└── Translator.php     # Multi-language support

public/
└── index.php          # Entry point

.env                   # Configuration file
Dockerfile             # FrankenPHP container
Caddyfile             # FrankenPHP/Caddy config
```

## Usage

### Basic Navigation
- Browse SMB shares and directories
- Upload and download files
- Create new folders
- Delete files and folders
- Rename files and folders

### Authentication
The application supports two authentication modes:
- Form-based authentication (default)
- HTTP Basic authentication

### Multi-language
Automatic language detection from browser or manual selection via `?lang=es` parameter.

## Development

### Run in development mode:
```bash
composer install
php -S localhost:8080 -t public
```

### Code Standards
The codebase follows:
- PSR-4 autoloading
- PSR-12 coding style
- Strict typing
- Modern PHP 8 features

## Migration from Legacy Version

The legacy `smbwebclient.php` file is preserved for reference. Key changes:

1. **Object-Oriented**: Moved from procedural to OOP
2. **Native SMB**: Using icewind/smb instead of CLI
3. **Type Safety**: All methods and properties are strictly typed
4. **Modern PHP**: Constructor property promotion, match expressions, etc.
5. **Dependency Management**: Composer for all dependencies
6. **Performance**: FrankenPHP for high-performance serving

## License

GPL-2.0-or-later

## Credits

Original author: Victor M. Varela <vmvarela@gmail.com>
Modern rewrite: 2025

## Contributing

Contributions are welcome! Please ensure:
- PHP 8.2+ compatibility
- Strict typing enabled
- PSR-12 coding standards
- Updated documentation

## Troubleshooting

### SMB Connection Issues
- Verify `SMB_DEFAULT_SERVER` is accessible
- Check credentials are correct
- Ensure libsmbclient is installed

### Permission Issues
- Ensure cache directory is writable
- Check SMB share permissions

### Performance
- Use FrankenPHP for best performance
- Enable OPcache in production
- Consider using mod_rewrite for cleaner URLs
