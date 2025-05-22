# Rust SMB Web Client

## Description

Rust SMB Web Client is a web-based interface for browsing SMB/CIFS shares. It allows users to navigate SMB servers, shares, and directories through a web browser. This project is inspired by the functionality of tools like `smbwebclient.php` but implemented in Rust for performance and safety, using the Actix web framework and `smbclient-rs` for SMB interactions.

## Features

Currently, the application supports the following key features:

-   **Web-based browsing:** Navigate SMB servers, shares, and directories.
-   **User Authentication:** Secure login with session management to access SMB shares.
-   **Secure Credential Handling:** Session data (including tokens derived from credentials) is stored in encrypted client-side cookies.
-   **File and Directory Listing:** View contents of shares and directories, including names, sizes (for files), and modification dates.
-   **Responsive UI:** Basic responsive design for navigation on different screen sizes.
-   **Configurable:** Application behavior can be configured using environment variables.

**Planned Features:**
Future development will focus on adding file operations such as:
-   File download
-   File upload
-   File and directory deletion
-   Item renaming
-   Directory creation

## Configuration

The application is configured via environment variables. Below is a list of available variables:

-   **`BIND_ADDRESS`**: The IP address and port the web server should bind to.
    -   Example: `127.0.0.1:8080` (default), `0.0.0.0:8080` (to listen on all interfaces).
-   **`DEFAULT_SMB_SERVER`**: (Optional) The default SMB server address (hostname or IP) to pre-fill or use if not specified during login.
    -   Example: `myserver.local` or `192.168.1.100`.
-   **`DEFAULT_SMB_SHARE`**: (Optional) The default SMB share name to use.
    -   Example: `public_docs`.
-   **`ALLOW_ANONYMOUS_BROWSING`**: (Optional) Set to `true` or `false`. Defaults to `false`.
    -   If `true`, it may allow listing of initial shares on an SMB server if the server itself permits anonymous share enumeration. However, browsing specific paths within shares typically still requires authentication.
-   **`SESSION_KEY_BASE64`**: **CRUCIAL FOR SECURITY.** This variable **must** be set to a Base64 encoded string representing a cryptographically strong random key of 32 or 64 bytes. This key is used to encrypt session cookies.
    -   **Generate a 64-byte key (recommended, encodes to 88 Base64 chars, but Actix Key::generate() uses 64 bytes, which is what our random generator does if this is not set. For 64 raw bytes, use `openssl rand -base64 48` which produces 64 base64 chars from 48 random bytes. For a key compatible with what Actix's `Key::generate()` uses (a 64-byte master key), you'd want 64 raw bytes, which `openssl rand -base64 48` provides. Let's stick to recommending 64 raw bytes.)**
        To generate a suitable key (64 random bytes, then Base64 encoded):
        ```bash
        openssl rand -base64 48
        ```
        (The `openssl rand 48` command generates 48 random bytes, which are then base64 encoded to 64 characters. Our internal fallback generator creates a 64-byte key, then base64 encodes that, resulting in an 88-character string. For consistency with `Key::generate()`'s typical output for `from()`, a 64-byte raw key is common.)
        **Important**: If this variable is not set, the application will generate a random key at runtime. This is **NOT SUITABLE FOR PRODUCTION** as the key will change on every application restart, invalidating all existing sessions.

## Prerequisites

-   Rust (stable version, e.g., 1.70+ recommended)
-   Cargo (comes with Rust)
-   An accessible SMB/CIFS server for testing functionality.

## Building

1.  Clone the repository (replace with actual URL when available):
    ```bash
    git clone https://example.com/your-repo/smb-web-rust.git
    cd smb-web-rust
    ```
2.  Build the application:
    ```bash
    cargo build --release
    ```
    The binary will be located at `./target/release/smb-handler-example`.

## Running

1.  **Set the `SESSION_KEY_BASE64` environment variable.** This is critical for security in production.
    ```bash
    # Example: Generate a key and export it (do this once and store the key securely)
    # SESSION_KEY_VALUE=$(openssl rand -base64 48) # Generates 64 Base64 characters
    # echo "Your session key: $SESSION_KEY_VALUE"
    export SESSION_KEY_BASE64="your_generated_base64_key_here" 
    ```

2.  Set other environment variables as needed:
    ```bash
    export BIND_ADDRESS="0.0.0.0:8080" # Listen on all network interfaces at port 8080
    # Optional:
    # export DEFAULT_SMB_SERVER="your_smb_server_ip_or_hostname"
    # export DEFAULT_SMB_SHARE="your_default_share_name"
    # export ALLOW_ANONYMOUS_BROWSING="true" 
    # export RUST_LOG="info" # For logging level (info, debug, error, etc.)
    ```

3.  Run the application:
    ```bash
    ./target/release/smb-handler-example
    ```

4.  Access the application in your web browser at `http://<your_server_ip_or_hostname>:<port>` (e.g., `http://localhost:8080` if running locally).

## Running Tests

To run the unit and integration tests:

```bash
cargo test
```
Make sure templates are accessible for web integration tests (they expect to find `templates/**/*` relative to the crate root, or `../templates/**/*` if run from a specific test context).

## Project Structure

A brief overview of the project's directory structure:

-   `src/main.rs`: Application entry point, Actix web server initialization, middleware setup, and route definitions.
-   `src/config.rs`: Handles loading of application configuration from environment variables (`AppConfig` struct).
-   `src/smb_handler.rs`: Core logic for interacting with SMB shares using the `smbclient-rs` library. Includes functions for listing shares/paths, file operations (planned), etc.
-   `src/web_handlers.rs`: Contains Actix request handlers that manage the application's web routes (e.g., login, logout, browsing).
-   `src/auth.rs`: Helper functions for managing SMB credentials and user sessions.
-   `src/tests/`: Contains integration and unit tests.
    -   `src/tests/web_integration_tests.rs`: Tests for web handlers simulating HTTP requests.
-   `templates/`: HTML templates managed by the Tera templating engine.
    -   `layout.html`: Base layout for all pages.
    -   `login.html`: Login form page.
    -   `browser.html`: Main page for browsing shares and directories.
-   `static/`: Static assets like CSS, JavaScript, and images.
    -   `static/images/`: Placeholder icons for different file/directory types.

This structure separates concerns, making the codebase more modular and maintainable.
