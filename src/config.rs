//! Manages application configuration.
//!
//! This module defines the `AppConfig` struct, which holds settings
//! loaded from environment variables or defaults. It includes configurations
//! for the web server binding, default SMB server details, and session management.

use base64::{encode as base64_encode, decode as base64_decode};
use rand::Rng;
// serde is not directly used here but is relevant for SmbCreds if it were part of this module.
// use serde::{Deserialize, Serialize};

/// Application configuration settings.
///
/// Holds all configurable parameters for the application, loaded from environment
/// variables or using sensible defaults.
#[derive(Clone, Debug, PartialEq)]
pub struct AppConfig {
    /// The network address and port to bind the web server to (e.g., "127.0.0.1:8080").
    pub bind_address: String,
    /// Optional default SMB server address to use if not specified by the user during login.
    pub default_smb_server: Option<String>,
    /// Optional default SMB share name to pre-fill or use.
    pub default_smb_share: Option<String>,
    /// If true, allows browsing of shares list without prior login. Specific share/path access still requires login.
    pub allow_anonymous_browsing: bool,
    /// Base64 encoded string for the session middleware's private key. Should be a strong random key.
    pub session_key_base64: String,
}

impl AppConfig {
    /// Loads the application configuration.
    ///
    /// Values are sourced from environment variables. If an environment variable is not set,
    /// a default value is used.
    ///
    /// Environment Variables:
    /// - `BIND_ADDRESS`: Web server bind address (e.g., "127.0.0.1:8080").
    /// - `DEFAULT_SMB_SERVER`: Default SMB server hostname or IP.
    /// - `DEFAULT_SMB_SHARE`: Default SMB share name.
    /// - `ALLOW_ANONYMOUS_BROWSING`: "true" or "false" to allow anonymous share listing.
    /// - `SESSION_KEY_BASE64`: A Base64 encoded string representing a 32 or 64-byte private key for session encryption.
    ///   If not set or invalid, a temporary random key is generated (this is insecure for production).
    ///
    /// # Returns
    /// An `AppConfig` instance.
    pub fn load() -> Self {
        let session_key_env = std::env::var("SESSION_KEY_BASE64");
        
        let session_key_base64 = match session_key_env {
            Ok(key_str) => {
                // Validate the provided key from environment
                match base64_decode(&key_str) {
                    Ok(decoded_key) => {
                        // Common key lengths for cryptographic operations (e.g., AES-256 needs 32 bytes, Actix Key::generate() produces 64)
                        if decoded_key.len() == 32 || decoded_key.len() == 64 {
                            log::info!("Using SESSION_KEY_BASE64 from environment ({} bytes decoded).", decoded_key.len());
                            key_str
                        } else {
                            log::warn!(
                                "SESSION_KEY_BASE64 from environment decodes to {} bytes, which is not 32 or 64. \
                                 Falling back to a randomly generated key. THIS IS INSECURE FOR PRODUCTION if unintended.",
                                decoded_key.len()
                            );
                            generate_random_base64_key()
                        }
                    }
                    Err(e) => {
                        log::warn!(
                            "Failed to decode SESSION_KEY_BASE64 from environment (Error: {:?}). \
                             Falling back to a randomly generated key. THIS IS INSECURE FOR PRODUCTION if unintended.",
                            e
                        );
                        generate_random_base64_key()
                    }
                }
            }
            Err(_) => { // SESSION_KEY_BASE64 not set in environment
                log::info!("SESSION_KEY_BASE64 not set in environment. Generating a random default key (OK for dev, NOT FOR PRODUCTION).");
                generate_random_base64_key()
            }
        };

        // Load other configuration values from environment or use defaults
        AppConfig {
            bind_address: std::env::var("BIND_ADDRESS").unwrap_or_else(|_| "127.0.0.1:8080".to_string()),
            default_smb_server: std::env::var("DEFAULT_SMB_SERVER").ok(),
            default_smb_share: std::env::var("DEFAULT_SMB_SHARE").ok(),
            allow_anonymous_browsing: std::env::var("ALLOW_ANONYMOUS_BROWSING")
                .unwrap_or_else(|_| "false".to_string()) // Default to "false" if not set
                .parse() // Attempt to parse as bool
                .unwrap_or(false), // Default to false if parsing fails
            session_key_base64,
        }
    }

    /// Decodes the Base64 encoded session key into raw bytes.
    ///
    /// This is used by the session middleware which requires the key in byte format.
    ///
    /// # Returns
    /// A `Result` containing the decoded `Vec<u8>` key bytes, or a `base64::DecodeError`.
    pub fn get_session_key_bytes(&self) -> Result<Vec<u8>, base64::DecodeError> {
        base64_decode(&self.session_key_base64)
    }
}

/// Generates a cryptographically random 64-byte key and returns it as a Base64 encoded string.
///
/// This is used as a fallback if `SESSION_KEY_BASE64` is not properly configured.
/// A 64-byte key is suitable for Actix's `Key::generate()` which is often used for AES-GCM.
fn generate_random_base64_key() -> String {
    let mut rng = rand::thread_rng();
    // Actix Key::generate() typically creates a 64-byte key.
    let key: [u8; 64] = rng.gen();
    base64_encode(&key)
}


#[cfg(test)]
mod tests {
    use super::*;
    use std::env;
    use std::sync::Mutex;

    // Mutex to synchronize environment variable changes for tests.
    // `cargo test` runs tests in parallel by default, which can lead to race conditions
    // when modifying shared state like environment variables.
    // Using a Mutex ensures that tests manipulating env vars run serially.
    // Alternatively, tests could be run with `cargo test -- --test-threads=1`.
    static ENV_LOCK: Mutex<()> = Mutex::new(());

    /// A helper struct to manage environment variables during tests.
    ///
    /// It sets specified environment variables before a test and restores
    /// their original state (or removes them) when it goes out of scope (Drop).
    struct EnvGuard {
        /// List of variable names that were set or cleared by this guard.
        vars_to_clear: Vec<String>,
        /// Original values of variables that were modified or cleared.
        original_values: Vec<(String, Option<String>)>,
    }

    impl EnvGuard {
        /// Creates a new `EnvGuard`.
        ///
        /// # Arguments
        /// * `vars_to_set`: A slice of key-value pairs to set as environment variables.
        /// * `vars_to_clear_initially`: A slice of variable names to clear before setting any new ones.
        ///   This ensures a clean state for these specific variables.
        fn new(vars_to_set: &[(&str, &str)], vars_to_clear_initially: &[&str]) -> Self {
            let _lock = ENV_LOCK.lock().unwrap(); // Acquire lock for safe env var manipulation
            let mut original_values = Vec::new();
            let mut vars_to_clear_on_drop = Vec::new();

            // First, clear variables specified in `vars_to_clear_initially`
            for var_name_str in vars_to_clear_initially {
                let var_name = var_name_str.to_string();
                original_values.push((var_name.clone(), env::var(&var_name).ok())); // Save original value
                env::remove_var(&var_name); // Remove var
                vars_to_clear_on_drop.push(var_name); // Mark for cleanup on drop
            }
            
            // Then, set new variables
            for (var_name_str, val) in vars_to_set {
                let var_name = var_name_str.to_string();
                // If this var wasn't in `vars_to_clear_initially`, save its original value now.
                if !vars_to_clear_initially.contains(var_name_str) {
                    original_values.push((var_name.clone(), env::var(&var_name).ok()));
                }
                env::set_var(&var_name, val); // Set new value
                // Ensure it's marked for cleanup if not already.
                if !vars_to_clear_on_drop.contains(&var_name) {
                    vars_to_clear_on_drop.push(var_name);
                }
            }
            
            EnvGuard { vars_to_clear: vars_to_clear_on_drop, original_values }
        }
    }

    impl Drop for EnvGuard {
        /// Restores the environment variables to their original state when `EnvGuard` is dropped.
        fn drop(&mut self) {
            let _lock = ENV_LOCK.lock().unwrap(); // Acquire lock
            // First, remove all variables that were explicitly set or cleared by this guard.
            for var_name in &self.vars_to_clear {
                env::remove_var(var_name);
            }
            // Then, restore the original values of all affected variables.
            for (var_name, original_value) in &self.original_values {
                if let Some(val) = original_value {
                    env::set_var(var_name, val);
                } else {
                    // If the original value was None (i.e., var was not set), ensure it's removed.
                    env::remove_var(var_name);
                }
            }
        }
    }

    /// Tests loading `AppConfig` when no relevant environment variables are set.
    /// Expects default values to be used.
    #[test]
    fn test_load_defaults() {
        // Ensure a clean environment for these specific variables during this test.
        let _guard = EnvGuard::new(&[], &["BIND_ADDRESS", "DEFAULT_SMB_SERVER", "DEFAULT_SMB_SHARE", "ALLOW_ANONYMOUS_BROWSING", "SESSION_KEY_BASE64"]);
        
        let config = AppConfig::load();
        assert_eq!(config.bind_address, "127.0.0.1:8080", "Default bind address should be used.");
        assert_eq!(config.default_smb_server, None, "Default SMB server should be None.");
        assert_eq!(config.default_smb_share, None, "Default SMB share should be None.");
        assert_eq!(config.allow_anonymous_browsing, false, "Default anonymous browsing should be false.");
        
        // Check that a session key was generated and is valid base64 decodable to 64 bytes.
        let decoded_key = base64_decode(&config.session_key_base64);
        assert!(decoded_key.is_ok(), "Generated session key should be valid base64.");
        assert_eq!(decoded_key.unwrap().len(), 64, "Generated session key should decode to 64 bytes, matching our random generator.");
    }

    /// Tests loading `AppConfig` from set environment variables.
    #[test]
    fn test_load_from_env() {
        let test_bind_address = "0.0.0.0:9090";
        let test_smb_server = "test.smb.server";
        let test_smb_share = "testshare";
        let test_anon_browsing = "true";
        // Generate a valid 64-byte key and base64 encode it for the test.
        let mut rng = rand::thread_rng();
        let key_bytes: [u8; 64] = rng.gen(); // Using 64 bytes as per our `generate_random_base64_key`
        let test_session_key = base64_encode(&key_bytes);

        let vars_to_set = [
            ("BIND_ADDRESS", test_bind_address),
            ("DEFAULT_SMB_SERVER", test_smb_server),
            ("DEFAULT_SMB_SHARE", test_smb_share),
            ("ALLOW_ANONYMOUS_BROWSING", test_anon_browsing),
            ("SESSION_KEY_BASE64", &test_session_key),
        ];
        // Clear these vars initially before setting them to ensure the test is clean.
        let _guard = EnvGuard::new(&vars_to_set, &["BIND_ADDRESS", "DEFAULT_SMB_SERVER", "DEFAULT_SMB_SHARE", "ALLOW_ANONYMOUS_BROWSING", "SESSION_KEY_BASE64"]);

        let config = AppConfig::load();
        assert_eq!(config.bind_address, test_bind_address);
        assert_eq!(config.default_smb_server, Some(test_smb_server.to_string()));
        assert_eq!(config.default_smb_share, Some(test_smb_share.to_string()));
        assert_eq!(config.allow_anonymous_browsing, true);
        assert_eq!(config.session_key_base64, test_session_key, "Session key from env should be used.");
    }

    /// Tests `AppConfig::load` when `SESSION_KEY_BASE64` is not valid Base64.
    /// Expects a fallback to a randomly generated key.
    #[test]
    fn test_load_invalid_session_key_not_base64() {
         let vars_to_set = [("SESSION_KEY_BASE64", "this is not base64, it has spaces and invalid chars like %!")];
         let _guard = EnvGuard::new(&vars_to_set, &["SESSION_KEY_BASE64"]);

        let config = AppConfig::load();
        // Should fall back to a random key.
        let decoded_key = base64_decode(&config.session_key_base64);
        assert!(decoded_key.is_ok(), "Fallback session key should be valid base64.");
        assert_eq!(decoded_key.unwrap().len(), 64, "Fallback session key should decode to 64 bytes.");
        assert_ne!(config.session_key_base64, "this is not base64, it has spaces and invalid chars like %!", "Original invalid key should not be used.");
    }

    /// Tests `AppConfig::load` when `SESSION_KEY_BASE64` is valid Base64 but decodes to an unsupported length.
    /// Expects a fallback to a randomly generated key.
    #[test]
    fn test_load_invalid_session_key_wrong_length() {
        // Valid base64, but decodes to 10 bytes (not 32 or 64, which are our accepted lengths).
        let short_key_bytes: [u8; 10] = rand::thread_rng().gen();
        let short_session_key = base64_encode(&short_key_bytes);

        let vars_to_set = [("SESSION_KEY_BASE64", short_session_key.as_str())];
        let _guard = EnvGuard::new(&vars_to_set, &["SESSION_KEY_BASE64"]);
        
        let config = AppConfig::load();
        // Should fall back to a random key.
        let decoded_key = base64_decode(&config.session_key_base64);
        assert!(decoded_key.is_ok(), "Fallback session key should be valid base64.");
        assert_eq!(decoded_key.unwrap().len(), 64, "Fallback session key should decode to 64 bytes.");
        assert_ne!(config.session_key_base64, short_session_key, "Original short key should not be used.");
    }

    /// Tests `AppConfig::load` with a valid 32-byte session key from environment.
    #[test]
    fn test_load_valid_32_byte_session_key() {
        let key_bytes_32: [u8; 32] = rand::thread_rng().gen();
        let session_key_32_b64 = base64_encode(&key_bytes_32);

        let vars_to_set = [("SESSION_KEY_BASE64", session_key_32_b64.as_str())];
         let _guard = EnvGuard::new(&vars_to_set, &["SESSION_KEY_BASE64"]);

        let config = AppConfig::load();
        assert_eq!(config.session_key_base64, session_key_32_b64, "The 32-byte session key from env should be used.");
        let decoded_key = base64_decode(&config.session_key_base64).unwrap();
        assert_eq!(decoded_key.len(), 32, "Decoded key should be 32 bytes long.");
    }
}
