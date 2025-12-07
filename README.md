<p align="center">
  <a href="https://github.com/vmvarela/smbwebclient/actions/workflows/php.yaml"><img src="https://github.com/vmvarela/smbwebclient/actions/workflows/php.yaml/badge.svg" alt="PHP CI"></a>
  <a href="https://github.com/vmvarela/smbwebclient/actions/workflows/test.yaml"><img src="https://github.com/vmvarela/smbwebclient/actions/workflows/test.yaml/badge.svg" alt="Docker Build"></a>
  <img src="https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg" alt="PHP Version">
  <a href="https://github.com/vmvarela/smbwebclient/blob/master/LICENSE"><img src="https://img.shields.io/github/license/vmvarela/smbwebclient" alt="License"></a>
</p>

# SMB Web Client

A modern PHP 8 web-based file browser for SMB/CIFS network shares, powered by FrankenPHP and the icewind/smb library.

## Features

- 🚀 **PHP 8.2+** with modern syntax (typed properties, constructor promotion, strict typing)
- 🔥 **FrankenPHP** powered by Go and Caddy for high performance
- 📦 **icewind/smb** library for native SMB/CIFS support
- 🎨 **Multiple themes** (Windows, macOS, Ubuntu)
- 🔒 **Session-based authentication** with credential management
- 🌍 **Multi-language support** (40+ languages with auto-detection)
- 📁 **File operations**: upload, download, create folders, delete, rename
- 🖱️ **Drag & drop** file uploads
- 🔄 **Sortable columns** (name, size, date, type)
- 🐳 **Docker ready** with docker-compose

## Requirements

- PHP 8.2 or higher
- libsmbclient library and PHP extension
- Composer

## Quick Start with Docker

### Using GitHub Container Registry (Recommended)

```bash
# Pull the image
docker pull ghcr.io/vmvarela/smbwebclient:latest

# Run with environment variables
docker run -d \
  -p 8080:80 \
  -e SMB_DEFAULT_SERVER=your-smb-server \
  ghcr.io/vmvarela/smbwebclient:latest

# Access at http://localhost:8080
```

### Using Docker Compose

Create a `docker-compose.yml` file:

```yaml
services:
  smbwebclient:
    image: ghcr.io/vmvarela/smbwebclient:latest
    ports:
      - "8080:80"
    volumes:
      - ./.env:/app/.env:ro
    environment:
      - SERVER_NAME=:80
    restart: unless-stopped
```

Then run:

```bash
# Create your .env file
cat > .env << EOF
SMB_DEFAULT_SERVER=your-smb-server
SMB_HIDE_SYSTEM_SHARES=true
APP_DEFAULT_LANGUAGE=en
EOF

# Start the container
docker-compose up -d

# Access at http://localhost:8080
```

### Building from Source

```bash
# Clone the repository
git clone https://github.com/vmvarela/smbwebclient.git
cd smbwebclient

# Copy and configure environment
cp .env.example .env
# Edit .env with your SMB server settings

# Start with Docker Compose
docker-compose up -d

# Access at http://localhost:8080
```

## Manual Installation

1. Install dependencies:
```bash
composer install
```

2. Configure environment:
```bash
cp .env.example .env
# Edit .env with your settings
```

3. Run with FrankenPHP:
```bash
frankenphp run --config Caddyfile
```

Or use PHP's built-in server for development:
```bash
php -S localhost:8080 -t public
```

## Configuration

All configuration is managed through environment variables in the `.env` file:

### SMB Settings

| Variable | Description | Default |
|----------|-------------|---------|
| `SMB_DEFAULT_SERVER` | Default SMB server hostname | `localhost` |
| `SMB_SERVER_LIST` | Comma-separated list of allowed servers | *(empty)* |
| `SMB_ROOT_PATH` | Root path to restrict navigation (e.g., `/server/share`) | *(empty)* |
| `SMB_HIDE_DOT_FILES` | Hide files starting with dot | `true` |
| `SMB_HIDE_SYSTEM_SHARES` | Hide system shares (C$, ADMIN$, IPC$) | `true` |
| `SMB_HIDE_PRINTER_SHARES` | Hide printer shares | `false` |

### Application Settings

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_DEFAULT_LANGUAGE` | Default UI language | `en` |
| `APP_DEFAULT_CHARSET` | Character encoding | `UTF-8` |
| `APP_CACHE_PATH` | Directory for temporary files | *(empty)* |
| `APP_SESSION_NAME` | Custom session cookie name | `SMBWebClientID` |
| `APP_ALLOW_ANONYMOUS` | Allow anonymous access | `false` |
| `APP_MOD_REWRITE` | Enable clean URLs | `false` |
| `APP_BASE_URL` | Base URL for clean URLs | *(empty)* |

### Other Settings

| Variable | Description | Default |
|----------|-------------|---------|
| `LOG_LEVEL` | Logging verbosity (0-3) | `0` |
| `LOG_FACILITY` | Syslog facility | `LOG_DAEMON` |

## Supported Languages

The application supports automatic language detection and includes translations for:

`af`, `ar`, `az`, `bg`, `bs`, `ca`, `cs`, `da`, `de`, `el`, `en`, `eo`, `es`, `et`, `eu`, `fa`, `fi`, `fr`, `gl`, `he`, `hi`, `hr`, `hu`, `id`, `it`, `ja`, `ko`, `ka`, `lt`, `lv`, `ms`, `nl`, `no`, `pl`, `pt`, `pt-br`, `ro`, `ru`, `sk`, `sl`, `sq`, `sr`, `sv`, `th`, `tr`, `uk`, `zh`, `zh-tw`

## Available Themes

- **Windows** - Classic Windows Explorer style
- **macOS** - Apple Finder style  
- **Ubuntu** - Nautilus file manager style

## Architecture

```
├── public/
│   ├── index.php          # Application entry point
│   └── assets/            # CSS, images
├── src/
│   ├── Application.php    # Main application controller
│   ├── Config.php         # Configuration management
│   ├── SmbClient.php      # SMB operations wrapper
│   ├── Session.php        # Session and authentication
│   └── Translator.php     # Multi-language support
├── cache/                 # Temporary files (ZIP downloads)
├── .env                   # Configuration file
├── Caddyfile              # FrankenPHP/Caddy config
├── Dockerfile             # FrankenPHP container
└── docker-compose.yml     # Docker Compose setup
```

## Docker Compose Services

The included `docker-compose.yml` provides:

- **smbwebclient**: Main application on port 8080
- **samba1**: Test Samba server with sample shares
- **samba2**: Additional test Samba server

Test credentials for Samba servers: `user` / `pass`

## Development

```bash
# Install dependencies
composer install

# Run development server
php -S localhost:8080 -t public

# Static analysis
vendor/bin/phpstan analyse src
```

## Code Standards

- PSR-4 autoloading
- PSR-12 coding style
- Strict typing enabled
- PHP 8.2+ features (constructor promotion, match expressions, named arguments)

## License

MIT License - Copyright (c) 2003-2025 Victor M. Varela

See [LICENSE](LICENSE) file for details.

## Author

Victor M. Varela <vmvarela@gmail.com>

## Troubleshooting

### Connection Issues
- Verify `SMB_DEFAULT_SERVER` is accessible from the container/server
- Check that credentials have appropriate permissions
- Ensure libsmbclient and PHP smbclient extension are installed

### Permission Issues
- Ensure the cache directory is writable
- Check SMB share permissions for the authenticating user

### Performance Tips
- Use FrankenPHP for best performance
- Enable OPcache in production
- Consider using `SMB_ROOT_PATH` to limit browsing scope
