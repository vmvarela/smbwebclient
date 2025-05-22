//! Main entry point for the SMB Web Browser application.
//!
//! This module initializes the application, including:
//! - Setting up logging (`env_logger`).
//! - Loading application configuration (`AppConfig`).
//! - Initializing the Tera templating engine.
//! - Configuring and starting the Actix web server with appropriate middleware (Logger, Session).
//! - Defining routes and mapping them to handlers in `web_handlers.rs`.
//! - Serving static files.

// Declare modules used by the application.
mod smb_handler;
mod config;
mod auth;
mod web_handlers;
#[cfg(test)] // Only compile the tests module when running cargo test
mod tests;

use actix_web::{web, App, HttpServer, middleware::Logger};
use actix_session::{SessionMiddleware, storage::CookieSessionStore};
use actix_web::cookie::Key;
use tera::Tera;
use crate::config::AppConfig; // Use `crate::` to refer to items in the current crate.

/// Main function for the Actix web server.
///
/// This asynchronous function sets up and runs the HTTP server.
/// It initializes logging, loads configuration, prepares the templating engine,
/// configures session management, and then binds to the specified address to
/// serve HTTP requests.
///
/// # Returns
/// An `std::io::Result<()>` which is `Ok(())` if the server runs and shuts down gracefully,
/// or an `Err` if the server fails to bind or start.
#[actix_web::main]
async fn main() -> std::io::Result<()> {
    // Initialize logger from environment variables (e.g., RUST_LOG=info).
    // Defaults to "info" level if RUST_LOG is not set.
    env_logger::Builder::from_env(env_logger::Env::default().default_filter_or("info")).init();
    log::info!("Starting SMB Web Browser application...");

    // Load application configuration from environment variables or defaults.
    let app_config = AppConfig::load();
    log::info!("Application configuration loaded: bind_address={}, default_smb_server={:?}",
               app_config.bind_address, app_config.default_smb_server);

    // Initialize Tera templating engine.
    // Templates are expected to be in the "templates" directory with glob pattern "**/*".
    // This means Tera will look for .html (or other) files in "templates" and its subdirectories.
    let tera = match Tera::new("templates/**/*") {
        Ok(t) => {
            log::info!("Tera templating engine initialized successfully.");
            t
        }
        Err(e) => {
            // If Tera fails to initialize (e.g., templates not found or invalid),
            // log the error and exit, as the application cannot render pages.
            log::error!("Failed to initialize Tera: {:?}. Ensure 'templates' directory exists and contains valid HTML files.", e);
            // Attempt to create templates directory if it doesn't exist for robustness during first run or dev.
            // This is a developer convenience; in production, deployment should ensure templates exist.
            if let Err(create_err) = std::fs::create_dir_all("templates") {
                 log::error!("Additionally, failed to create 'templates' directory: {:?}", create_err);
            }
            // Critical error, so exit the application.
            return Err(std::io::Error::new(std::io::ErrorKind::Other, format!("Tera initialization failed: {}", e)));
        }
    };

    // Load and decode the session key from AppConfig.
    // The key is stored in Base64 in config and needs to be decoded for session middleware.
    let session_key_bytes = match app_config.get_session_key_bytes() {
        Ok(bytes) => {
            // Actix's Key::from(&[u8]) can derive a key.
            // It's recommended to use a key of sufficient length (e.g., 64 bytes for AES-GCM used by Key::generate()).
            if bytes.len() != 32 && bytes.len() != 64 { // 32 bytes for AES-256, 64 for default Actix Key generation
                log::warn!(
                    "Decoded session key is {} bytes long. Recommended lengths are 32 or 64 bytes for robust security. CookieSession might be less secure or panic.",
                    bytes.len()
                );
            }
            // Key::from will panic on an empty slice. If bytes are empty, fallback to a random key.
            if bytes.is_empty() {
                log::error!("Decoded session key is empty! This is insecure. Using a temporary random key for this session only.");
                Key::generate() // Generate a random 64-byte key.
            } else {
                Key::from(&bytes) // Create key from decoded bytes.
            }
        }
        Err(e) => {
            // If decoding fails (e.g., invalid Base64 string), log error and use a temporary random key.
            // THIS IS INSECURE FOR PRODUCTION if the configured key is consistently invalid.
            log::error!("Failed to decode session key from base64: {:?}. Using a temporary random key. THIS IS INSECURE FOR PRODUCTION.", e);
            Key::generate() // Fallback to a random 64-byte key.
        }
    };

    log::info!("Setting up HTTP server on {}...", app_config.bind_address);

    // Start the HTTP server.
    HttpServer::new(move || {
        // `move` closure captures variables from the surrounding scope by value (e.g., app_config, tera, session_key_bytes).

        // Configure session middleware.
        // `CookieSessionStore` stores session data in encrypted client-side cookies.
        // `.secure(false)` is for development over HTTP. For production, use `true` and ensure HTTPS is enabled.
        let session_mw = SessionMiddleware::builder(CookieSessionStore::default(), session_key_bytes.clone())
            .cookie_name("smb-browser-session".to_string()) // Custom cookie name
            .cookie_secure(app_config.bind_address.starts_with("https://")) // Set secure flag based on bind address (basic heuristic) or a dedicated config field
            .cookie_http_only(true) // Prevent client-side JavaScript access to the cookie.
            .cookie_same_site(actix_web::cookie::SameSite::Lax) // CSRF protection.
            .build();

        App::new()
            // Enable Actix's request logger middleware.
            .wrap(Logger::default()) // Default format.
            .wrap(Logger::new("%a %{User-Agent}i %r %s %b %T")) // More detailed custom format.
            // Enable session middleware.
            .wrap(session_mw)
            // Share `AppConfig` and `Tera` instances with all handlers via `app_data`.
            .app_data(web::Data::new(app_config.clone())) // Clone `app_config` for this worker thread.
            .app_data(web::Data::new(tera.clone()))       // Clone `tera` for this worker thread.
            
            // Serve static files (CSS, JavaScript, images) from the "static" directory.
            // Files under "/static/..." URL path will be served from the "./static/" directory.
            .service(actix_files::Files::new("/static", "static").show_files_listing()) // show_files_listing is useful for dev, consider removing for prod.
            
            // Define application routes and map them to handler functions.
            .route("/", web::get().to(web_handlers::index_redirect)) // Root path redirects to browse.
            .route("/login", web::get().to(web_handlers::login_get))     // Display login page.
            .route("/login", web::post().to(web_handlers::login_post))    // Handle login form submission.
            .route("/logout", web::get().to(web_handlers::logout))    // Handle logout.
            .route("/browse", web::get().to(web_handlers::browse))      // Handles "/browse/" (empty path for server/share listing).
            .route("/browse/{path:.*}", web::get().to(web_handlers::browse)) // Handles "/browse/server/share/path..."
    })
    .bind(&app_config.bind_address)? // Bind server to the address from config.
    .run() // Run the server.
    .await // Await server shutdown.
}
