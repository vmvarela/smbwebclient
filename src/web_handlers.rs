//! Handles HTTP requests for the web interface.
//!
//! This module defines Actix web handlers for user authentication (login/logout)
//! and for browsing SMB shares and directories. It uses Tera for HTML template
//! rendering and interacts with `smb_handler` for SMB operations and `auth`
//! for session management.

use actix_web::{web, HttpResponse, Responder, Result as ActixResult}; // Renamed App import as it's not used directly here.
use actix_session::Session;
use tera::Tera;
use serde::Deserialize;
use crate::smb_handler::{self, SmbCreds, AppError, ShareInfo, DirectoryEntry, EntryType};
use crate::auth::{get_smb_credentials, set_smb_credentials, clear_smb_credentials};
use crate::config::AppConfig;

/// Data structure for capturing form data from the login page.
#[derive(Deserialize, Debug)]
pub struct LoginFormData {
    /// The username provided by the user.
    username: String,
    /// The password provided by the user.
    password: String,
    /// Optional SMB domain/workgroup.
    domain: Option<String>,
    /// Optional SMB server address. If not provided, the application's default server is used.
    server: Option<String>,
}

/// Enum to wrap different types of items that can be displayed in the browser template.
///
/// This allows a heterogeneous list of items (shares or directory entries) to be passed
/// to Tera, which can then use `serde(untagged)` and template logic to render them correctly.
#[derive(serde::Serialize)]
#[serde(untagged)] // Allows Tera to match based on fields present.
enum BrowserItem {
    /// Represents an SMB share.
    Share {
        // Fields from smb_handler::ShareInfo
        name: String,
        share_type: String,
        comment: String,
        /// Flag to help templates distinguish this variant.
        is_share_item: bool,
    },
    /// Represents a file or directory entry within a share.
    Entry {
        // Fields from smb_handler::DirectoryEntry
        name: String,
        entry_type: EntryType, // EntryType should also derive Serialize.
        size: u64,
        modified: i64,
        /// The full path of this entry relative to the root of its share.
        full_path: String,
        /// Flag to help templates distinguish this variant.
        is_share_item: bool,
    },
}

// --- Converters for BrowserItem ---

impl From<ShareInfo> for BrowserItem {
    /// Converts an `smb_handler::ShareInfo` into a `BrowserItem::Share`.
    fn from(share: ShareInfo) -> Self {
        BrowserItem::Share {
            name: share.name,
            share_type: share.share_type,
            comment: share.comment,
            is_share_item: true, // Mark as a share item
        }
    }
}

/// Helper struct to combine `DirectoryEntry` with its full path within the share.
/// This is needed because `DirectoryEntry` itself only knows its name, not its location.
struct DirectoryEntryWithPath {
    entry: DirectoryEntry,
    item_path_in_share: String, // The full path of this item relative to the share's root.
}
impl From<DirectoryEntryWithPath> for BrowserItem {
    /// Converts a `DirectoryEntryWithPath` (an `smb_handler::DirectoryEntry` with its path context)
    /// into a `BrowserItem::Entry`.
    fn from(dwp: DirectoryEntryWithPath) -> Self {
        BrowserItem::Entry {
            name: dwp.entry.name,
            entry_type: dwp.entry.entry_type,
            size: dwp.entry.size,
            modified: dwp.entry.modified,
            full_path: dwp.item_path_in_share,
            is_share_item: false, // Mark as not a share item
        }
    }
}

// --- Web Handlers ---

/// Handles `GET /login` requests.
///
/// Renders the login page. If a default server is configured, it's passed to the template.
/// If the user is already logged in (credentials in session), this information might be
/// displayed or used by the template.
///
/// # Arguments
/// * `tera`: Shared Tera templating engine instance.
/// * `config`: Shared application configuration.
/// * `session`: Current user session.
///
/// # Returns
/// An `ActixResult<HttpResponse>` which is the rendered HTML page or an error response.
pub async fn login_get(tera: web::Data<Tera>, config: web::Data<AppConfig>, session: Session) -> ActixResult<HttpResponse> {
    let mut ctx = tera::Context::new();
    ctx.insert("default_server", &config.default_smb_server.as_deref().unwrap_or(""));
    // Check if user is already logged in to potentially show different info
    if let Some(creds) = get_smb_credentials(&session) {
        let mut user_info = std::collections::HashMap::new();
        user_info.insert("username", creds.user.clone());
        // Add target_smb_server if available in session, to show what server they are logged into
        if let Ok(Some(server)) = session.get::<String>("target_smb_server") {
            user_info.insert("server", server);
        }
        ctx.insert("current_user_info", &user_info);
    }
    let rendered = tera.render("login.html", &ctx).map_err(|e| {
        log::error!("Template error (login_get): {:?}", e);
        actix_web::error::ErrorInternalServerError("Template error rendering login.html")
    })?;
    Ok(HttpResponse::Ok().content_type("text/html").body(rendered))
}

/// Handles `POST /login` requests.
///
/// Attempts to authenticate the user against an SMB server using the provided credentials.
/// On successful authentication, credentials (excluding server address) and the target server address
/// are stored in the session, and the user is redirected to the main browsing page (`/browse/`).
/// On failure, the login page is re-rendered with an error message.
///
/// # Arguments
/// * `form`: Submitted form data (`LoginFormData`).
/// * `session`: Current user session.
/// * `config`: Shared application configuration.
/// * `tera`: Shared Tera templating engine instance.
/// * `mock_responses` (test-only): Injected mock data for testing without a live SMB server.
///
/// # Returns
/// An `ActixResult<HttpResponse>`, typically a redirect or a re-rendered login page.
pub async fn login_post(
    form: web::Form<LoginFormData>,
    session: Session,
    config: web::Data<AppConfig>,
    tera: web::Data<Tera>,
    // mock_responses: web::Data<Arc<MockSmbResponses>>, // This line is for testing, remove or conditionalize for production
) -> ActixResult<HttpResponse> {
    // Determine the SMB server address to use: form input > app default.
    let smb_server_to_try = form.server.as_ref()
        .filter(|s| !s.is_empty()) // Use server from form if provided and not empty
        .or(config.default_smb_server.as_ref()) // Otherwise, use default from config
        .cloned(); // Clone the Option<String>

    // If no server address can be determined, return an error.
    if smb_server_to_try.is_none() {
        log::warn!("Login attempt failed: No SMB server specified and no default configured.");
        let mut ctx = tera::Context::new();
        ctx.insert("error_message", "Login failed: No SMB server specified and no default configured.");
        ctx.insert("default_server", ""); // No default to offer
        let rendered = tera.render("login.html", &ctx).map_err(actix_web::error::ErrorInternalServerError)?;
        return Ok(HttpResponse::BadRequest().content_type("text/html").body(rendered));
    }
    let server_address = smb_server_to_try.unwrap(); // Safe due to the check above.

    let creds = SmbCreds {
        user: form.username.clone(),
        pass: form.password.clone(),
        domain: form.domain.clone().filter(|d| !d.is_empty()), // Store None if domain string is empty
    };

    log::info!("Attempting login for user '{}' on server '{}'", creds.user, server_address);

    // Attempt to list shares on the server to verify credentials. This is a common way to "ping" or test auth.
    match smb_handler::list_shares(&server_address, &creds).await {
    // Production code:
    // match smb_handler::list_shares(&server_address, &creds).await {
    // Test code using mocks (if you were to use the mock_responses parameter):
    // match mock_list_shares(&server_address, &creds, &mock_responses).await {
        Ok(_) => { // Authentication successful
            log::info!("Login successful for user '{}' on server '{}'", creds.user, server_address);
            // Store credentials (excluding server) and the successfully targeted server in session.
            if let Err(e) = set_smb_credentials(&session, &creds) {
                log::error!("Failed to set SMB credentials in session: {:?}", e);
                let mut ctx_err = tera::Context::new();
                ctx_err.insert("error_message", "Session error, please try again.");
                ctx_err.insert("default_server", &config.default_smb_server.as_deref().unwrap_or(""));
                let rendered_err = tera.render("login.html", &ctx_err).map_err(actix_web::error::ErrorInternalServerError)?;
                return Ok(HttpResponse::InternalServerError().content_type("text/html").body(rendered_err));
            }
            if let Err(e) = session.insert("target_smb_server", &server_address) {
                 log::error!("Failed to set target_smb_server in session: {:?}", e);
                 // Non-fatal, but log it. User might get default server behavior if this fails.
            }
            // Redirect to the main browsing interface.
            Ok(HttpResponse::Found().append_header(("Location", "/browse/")).finish())
        }
        Err(e) => { // Authentication or connection failed
            log::warn!("Login failed for user '{}' on server '{}': {:?}", creds.user, server_address, e);
            let mut ctx_err = tera::Context::new();
            // Provide a user-friendly error message based on the type of error.
            let user_friendly_error = match e {
                AppError::AuthFailure => "Authentication failed: Invalid username, password, or domain.".to_string(),
                AppError::SmbError(smb_e) => format!("SMB connection error: {}. Check server address and availability.", smb_e),
                AppError::IoError(io_e) => format!("Network or I/O error: {}. Check network connection.", io_e),
                _ => "Login failed due to an unexpected error. Please try again.".to_string(),
            };
            ctx_err.insert("error_message", &user_friendly_error);
            // Pre-fill form with attempted values for user convenience.
            ctx_err.insert("default_server", &server_address); // Show the server they attempted
            ctx_err.insert("username", &form.username);
            ctx_err.insert("domain", &form.domain.as_deref().unwrap_or(""));

            let rendered = tera.render("login.html", &ctx_err).map_err(actix_web::error::ErrorInternalServerError)?;
            Ok(HttpResponse::Unauthorized().content_type("text/html").body(rendered))
        }
    }
}

/// Handles `GET /logout` requests.
///
/// Clears SMB credentials and the target server from the session, effectively logging the user out.
/// Redirects the user to the login page.
///
/// # Arguments
/// * `session`: Current user session.
///
/// # Returns
/// An `ActixResult<HttpResponse>` which is a redirect to the login page.
pub async fn logout(session: Session) -> ActixResult<HttpResponse> {
    log::info!("User logging out");
    clear_smb_credentials(&session);
    session.remove("target_smb_server"); // Remove the stored server as well
    Ok(HttpResponse::Found().append_header(("Location", "/login")).finish())
}

/// Represents the parsed components of an SMB path derived from a URL segment.
#[derive(Debug, PartialEq)]
struct ParsedSmbPath {
    /// The SMB server address (hostname or IP). `None` if it should be inferred from session or default config.
    server: Option<String>,
    /// The name of the SMB share. `None` if the path refers to the server level (to list shares).
    share: Option<String>,
    /// The path within the share (e.g., "folder/subfolder/file.txt"). Empty if at the root of a share or server.
    path_in_share: String,
}

/// Parses an SMB path from a URL segment string.
///
/// The `url_segment` is the part of the URL path that comes *after* `/browse/`.
/// It determines the server, share, and path within the share to be browsed.
///
/// Logic:
/// - An empty segment or "/" implies listing shares on the `default_server` (or session server).
/// - "server_name": Lists shares on `server_name`.
/// - "share_name" (with `default_server`): Lists content of `share_name` on `default_server`.
/// - "server_name/share_name": Lists content of `share_name` on `server_name`.
/// - "server_name/share_name/path": Lists content of `path` within `share_name` on `server_name`.
/// - "share_name/path" (with `default_server`): Lists content of `path` in `share_name` on `default_server`.
///
/// # Arguments
/// * `url_segment`: The raw string from the URL path (e.g., "myserver/docs/report.pdf" or "docs/report.pdf").
/// * `default_server`: An optional default server name. If provided, paths with fewer components
///   are interpreted relative to this server (e.g., "docs" becomes "default_server/docs").
///
/// # Returns
/// A `ParsedSmbPath` struct.
fn parse_smb_path_from_url_segment(url_segment: &str, default_server: Option<&String>) -> ParsedSmbPath {
    let trimmed_path = url_segment.trim_matches('/'); // Remove leading/trailing slashes
    // Split into at most 3 parts: server, share, path_in_share
    let mut parts_iter = trimmed_path.splitn(3, '/');
    
    // Extract up to three potential parts from the iterator
    let p1 = parts_iter.next().filter(|s| !s.is_empty()); // First part (server or share)
    let p2 = parts_iter.next().filter(|s| !s.is_empty()); // Second part (share or first path component)
    let p3 = parts_iter.next().filter(|s| !s.is_empty()); // Third part (path_in_share or continuation of it)

    match (p1, p2, p3) {
        // Case 1: Empty or "/" path -> Use default server, list shares.
        (None, _, _) => {
            ParsedSmbPath { server: default_server.cloned(), share: None, path_in_share: "".to_string() }
        }
        // Case 2: One part (e.g., "myserver" or "myshare")
        (Some(s1), None, _) => {
            if default_server.is_some() {
                // With a default server, a single part is treated as a share on that server.
                ParsedSmbPath { server: default_server.cloned(), share: Some(s1.to_string()), path_in_share: "".to_string() }
            } else {
                // No default server, so the single part must be the server itself (list its shares).
                ParsedSmbPath { server: Some(s1.to_string()), share: None, path_in_share: "".to_string() }
            }
        }
        // Case 3: Two parts (e.g., "myserver/myshare" or "myshare/folder")
        (Some(s1), Some(s2), None) => {
            if default_server.map_or(false, |ds_val| ds_val == s1) {
                // If s1 matches the default_server, then s1 is server, s2 is share.
                ParsedSmbPath { server: Some(s1.to_string()), share: Some(s2.to_string()), path_in_share: "".to_string() }
            } else if default_server.is_some() {
                // Default server exists but s1 is not it, so s1 is a share on default_server, s2 is path.
                ParsedSmbPath { server: default_server.cloned(), share: Some(s1.to_string()), path_in_share: s2.to_string() }
            } else {
                // No default server, so s1 is server, s2 is share.
                ParsedSmbPath { server: Some(s1.to_string()), share: Some(s2.to_string()), path_in_share: "".to_string() }
            }
        }
        // Case 4: Three parts (e.g., "myserver/myshare/folder" or "myshare/folder/subfolder")
        (Some(s1), Some(s2), Some(s3)) => {
            if default_server.map_or(false, |ds_val| ds_val == s1) {
                // s1 matches default_server: s1=server, s2=share, s3=path.
                ParsedSmbPath { server: Some(s1.to_string()), share: Some(s2.to_string()), path_in_share: s3.to_string() }
            } else if default_server.is_some() {
                // Default server exists but s1 is not it: s1=share on default_server, s2/s3 is path.
                ParsedSmbPath { server: default_server.cloned(), share: Some(s1.to_string()), path_in_share: format!("{}/{}", s2, s3) }
            } else {
                // No default server: s1=server, s2=share, s3=path.
                ParsedSmbPath { server: Some(s1.to_string()), share: Some(s2.to_string()), path_in_share: s3.to_string() }
            }
        }
    }
}

/// Handles `GET /browse/{path:.*}` requests.
///
/// This is the main handler for browsing SMB shares. It parses the `{path:.*}` segment
/// to determine the target server, share, and path within the share.
/// - If no specific share is identified, it lists shares on the target server.
/// - If a share is identified, it lists the contents of the specified path within that share.
/// Requires authentication unless `allow_anonymous_browsing` is true (for listing shares only).
/// Renders the `browser.html` template with the retrieved items.
///
/// # Arguments
/// * `path_param`: The dynamic path segment from the URL (captured by `/{path:.*}`).
/// * `session`: Current user session.
/// * `tera`: Shared Tera templating engine instance.
/// * `config`: Shared application configuration.
///
/// # Returns
/// An `ActixResult<HttpResponse>` which is the rendered browser page or an error/redirect.
pub async fn browse(
    path_param: web::Path<String>,
    session: Session,
    tera: web::Data<Tera>,
    config: web::Data<AppConfig>,
) -> ActixResult<HttpResponse> {
    let original_url_segment = path_param.into_inner(); // The string captured by {path:.*}
    log::info!("Browsing URL segment: /browse/{}", original_url_segment);

    let mut ctx = tera::Context::new();
    let mut current_user_info = std::collections::HashMap::new(); // For template user info

    // Check for SMB credentials in session
    let creds_opt = get_smb_credentials(&session);

    // If no credentials and anonymous browsing is disallowed, redirect to login.
    if creds_opt.is_none() && !config.allow_anonymous_browsing {
        log::info!("No credentials in session and anonymous browsing disallowed. Redirecting to login.");
        return Ok(HttpResponse::Found().append_header(("Location", "/login")).finish());
    }
    
    // If credentials exist, add user info to template context
    if let Some(creds) = &creds_opt {
        current_user_info.insert("username".to_string(), creds.user.clone());
        // Add target server to user info if available
        if let Ok(Some(server)) = session.get::<String>("target_smb_server") {
             current_user_info.insert("server".to_string(), server);
        }
        ctx.insert("current_user_info", &current_user_info);
    }

    // Determine the effective default server: session's target server > app config default.
    let server_from_session: Option<String> = session.get("target_smb_server").unwrap_or(None);
    let effective_default_server = server_from_session.as_ref().or(config.default_smb_server.as_ref());

    // Parse the URL segment to determine server, share, and path_in_share.
    let parsed_path = parse_smb_path_from_url_segment(&original_url_segment, effective_default_server);
    
    // Ensure a server could be determined.
    let server_to_use = match &parsed_path.server {
        Some(s) => s.clone(),
        None => { 
            log::error!("No SMB server could be determined for browsing. URL segment: '{}'", original_url_segment);
            ctx.insert("error_message", "No SMB server specified or configured for this request. Please login again or check server configuration.");
            let rendered = tera.render("browser.html", &ctx).map_err(|e| {
                log::error!("Template error (browse - no server): {:?}", e);
                actix_web::error::ErrorInternalServerError("Template error")
            })?;
            return Ok(HttpResponse::Ok().content_type("text/html").body(rendered));
        }
    };

    // --- Prepare context for Tera template ---
    ctx.insert("server_being_browsed", &server_to_use);
    ctx.insert("original_url_segment", &original_url_segment); 

    // Variables for "Up one level" navigation link
    let mut show_up_link = false;
    // Default "up" link goes to the server level (list shares of `server_to_use`)
    let mut parent_path_display = format!("/browse/{}", server_to_use); 

    if let Some(share_name) = &parsed_path.share {
        // We are browsing within a specific share.
        ctx.insert("share_being_browsed", share_name);
        ctx.insert("path_in_share", &parsed_path.path_in_share);
        log::info!("Listing path '{}/{}' on server '{}'", share_name, parsed_path.path_in_share, server_to_use);
        
        // Credentials are required to list contents within a share.
        let creds = match creds_opt {
            Some(c) => c,
            None => { // Should not happen if allow_anonymous_browsing is false (checked earlier)
                      // If it's true, this means trying to access share content anonymously.
                log::warn!("Attempting to list path '{}' in share '{}' without credentials (anonymous browsing for shares only). Redirecting to login.", parsed_path.path_in_share, share_name);
                return Ok(HttpResponse::Found().append_header(("Location", "/login?error=login_required_for_path")).finish());
            }
        };

        // Determine "Up" link logic when inside a share
        if !parsed_path.path_in_share.is_empty() {
            // If there's a path within the share, "up" link is visible.
            show_up_link = true;
            let path_parts: Vec<&str> = parsed_path.path_in_share.trim_matches('/').split('/').collect();
            if path_parts.len() > 1 {
                // Path has multiple segments (e.g., "folder/subfolder"), go up to parent folder.
                parent_path_display = format!("/browse/{}/{}/{}", server_to_use, share_name, path_parts[..path_parts.len()-1].join("/"));
            } else { 
                // Path has one segment (e.g., "folder"), go up to share root.
                parent_path_display = format!("/browse/{}/{}", server_to_use, share_name);
            }
        } else { 
            // At the root of a share, "up" link still visible, goes to server (list shares).
            show_up_link = true; 
            // `parent_path_display` is already correctly set to server level.
        }

        // Fetch directory entries for the path.
        match smb_handler::list_path(&server_to_use, share_name, &parsed_path.path_in_share, &creds).await {
            Ok(entries) => {
                let browser_items: Vec<BrowserItem> = entries.into_iter().map(|e| {
                    // Construct the full path of the item relative to the share root for linking.
                    let item_full_path_in_share = if parsed_path.path_in_share.is_empty() {
                        e.name.clone()
                    } else {
                        format!("{}/{}", parsed_path.path_in_share.trim_matches('/'), e.name)
                    };
                    BrowserItem::from(DirectoryEntryWithPath {
                        entry: e,
                        item_path_in_share: item_full_path_in_share,
                    })
                }).collect();
                ctx.insert("items", &browser_items);
            }
            Err(e) => {
                log::error!("Error listing path '{}/{}' on server '{}': {:?}", share_name, parsed_path.path_in_share, server_to_use, e);
                ctx.insert("error_message", &format!("Error listing directory contents: {}", e));
            }
        }
    } else { 
        // Listing shares on the server (`parsed_path.share` is None).
        ctx.insert("is_listing_shares", &true);
        log::info!("Listing shares on server '{}'", server_to_use);
        show_up_link = false; // No "up" link when at server level listing shares.
        
        // Credentials might be required by smb_handler even for listing shares,
        // or if anonymous browsing is disabled (already checked).
        let creds = match creds_opt {
            Some(c) => c,
            None => { // Anonymous browsing is allowed (config.allow_anonymous_browsing is true)
                      // but smb_handler::list_shares still needs some SmbCreds structure.
                      // This implies we need a way to pass "guest" or "anonymous" credentials.
                      // For now, if truly anonymous (no session creds), redirect to login as a fallback,
                      // as list_shares will likely fail without any creds.
                log::warn!("Attempting to list shares on '{}' anonymously (no session creds). This may require specific guest credentials not yet implemented. Redirecting to login.", server_to_use);
                return Ok(HttpResponse::Found().append_header(("Location", "/login?error=login_for_anon_shares")).finish());
            }
        };

        // Fetch shares for the server.
        match smb_handler::list_shares(&server_to_use, &creds).await {
            Ok(shares) => {
                let browser_items: Vec<BrowserItem> = shares.into_iter().map(BrowserItem::from).collect();
                ctx.insert("items", &browser_items);
            }
            Err(e) => {
                log::error!("Error listing shares on server '{}': {:?}", server_to_use, e);
                ctx.insert("error_message", &format!("Error listing shares: {}", e));
            }
        }
    }
    
    // Final context variables for the template.
    ctx.insert("show_up_link", &show_up_link);
    ctx.insert("parent_path_display", &parent_path_display);

    // Prepare path segments for breadcrumb navigation in the template.
    // These segments are relative to the share, if a share is being browsed.
    let path_segments_for_template = if parsed_path.share.is_some() && !parsed_path.path_in_share.is_empty() {
        parsed_path.path_in_share.split('/').filter(|s| !s.is_empty()).map(String::from).collect::<Vec<String>>()
    } else {
        Vec::new() // Empty if at share root or listing shares.
    };
    ctx.insert("path_segments", &path_segments_for_template);

    // Construct the full display path shown to the user (e.g., "/myserver/myshare/folder").
    let mut current_display_path = format!("/{}", server_to_use);
    if let Some(share) = &parsed_path.share {
        current_display_path.push('/');
        current_display_path.push_str(share);
        if !parsed_path.path_in_share.is_empty() {
            current_display_path.push('/');
            current_display_path.push_str(&parsed_path.path_in_share.trim_matches('/'));
        }
    }
    ctx.insert("current_display_path", &current_display_path);

    // Render the browser template with the collected context.
    let rendered = tera.render("browser.html", &ctx).map_err(|e| {
        log::error!("Template error (browse): {:?}", e);
        actix_web::error::ErrorInternalServerError("Template error rendering browser.html")
    })?;
    Ok(HttpResponse::Ok().content_type("text/html").body(rendered))
}

/// Handles `GET /` requests (root path).
///
/// Redirects the user to the main browsing interface (`/browse/`).
///
/// # Returns
/// An `HttpResponse` that performs a redirect.
pub async fn index_redirect() -> impl Responder {
    HttpResponse::Found().append_header(("Location", "/browse/")).finish()
}


#[cfg(test)]
mod tests {
    use super::*;

    // --- Unit Tests for parse_smb_path_from_url_segment ---

    /// Tests parsing of empty or root-like URL segments.
    #[test]
    fn test_parse_empty_path() {
        let default_server = Some("DEFAULT_SERVER".to_string());
        // Case 1: Empty string with default server.
        assert_eq!(
            parse_smb_path_from_url_segment("", default_server.as_ref()),
            ParsedSmbPath { server: Some("DEFAULT_SERVER".to_string()), share: None, path_in_share: "".to_string() }
        );
        // Case 2: Single slash with default server.
        assert_eq!(
            parse_smb_path_from_url_segment("/", default_server.as_ref()),
            ParsedSmbPath { server: Some("DEFAULT_SERVER".to_string()), share: None, path_in_share: "".to_string() }
        );
        // Case 3: Multiple slashes with default server.
        assert_eq!(
            parse_smb_path_from_url_segment("///", default_server.as_ref()),
            ParsedSmbPath { server: Some("DEFAULT_SERVER".to_string()), share: None, path_in_share: "".to_string() }
        );
        // Case 4: Empty string with no default server.
         assert_eq!(
            parse_smb_path_from_url_segment("", None),
            ParsedSmbPath { server: None, share: None, path_in_share: "".to_string() }
        );
    }

    /// Tests parsing URL segments that should resolve to a server name only (listing shares).
    #[test]
    fn test_parse_server_only() {
        // Case 1: "MYSERVER" with no default server.
        assert_eq!(
            parse_smb_path_from_url_segment("MYSERVER", None),
            ParsedSmbPath { server: Some("MYSERVER".to_string()), share: None, path_in_share: "".to_string() }
        );
        // Case 2: "MYSERVER/" with trailing slash, no default server.
        assert_eq!(
            parse_smb_path_from_url_segment("MYSERVER/", None),
            ParsedSmbPath { server: Some("MYSERVER".to_string()), share: None, path_in_share: "".to_string() }
        );
        // Case 3: "MYSHARE_AS_SERVER" with a default server present.
        // The segment is treated as a share on the default server.
        let default_s = Some("DEFAULT".to_string());
        assert_eq!(
            parse_smb_path_from_url_segment("MYSHARE_AS_SERVER", default_s.as_ref()),
            ParsedSmbPath { server: Some("DEFAULT".to_string()), share: Some("MYSHARE_AS_SERVER".to_string()), path_in_share: "".to_string() }
        );
    }

    /// Tests parsing URL segments like "server/share".
    #[test]
    fn test_parse_server_and_share() {
        // Case 1: "MYSERVER/MYSHARE" with no default server.
        assert_eq!(
            parse_smb_path_from_url_segment("MYSERVER/MYSHARE", None),
            ParsedSmbPath { server: Some("MYSERVER".to_string()), share: Some("MYSHARE".to_string()), path_in_share: "".to_string() }
        );
        // Case 2: "MYSERVER/MYSHARE" with a different default server.
        // The explicit server in path takes precedence.
        let default_s = Some("DEFAULT".to_string());
        assert_eq!(
            parse_smb_path_from_url_segment("MYSERVER/MYSHARE", default_s.as_ref()),
            ParsedSmbPath { server: Some("MYSERVER".to_string()), share: Some("MYSHARE".to_string()), path_in_share: "".to_string() }
        );
        // Case 3: "DEFAULT/MYSHARE" where "DEFAULT" matches the default server.
        // Correctly identifies "DEFAULT" as server and "MYSHARE" as share.
        assert_eq!(
            parse_smb_path_from_url_segment("DEFAULT/MYSHARE", default_s.as_ref()),
            ParsedSmbPath { server: Some("DEFAULT".to_string()), share: Some("MYSHARE".to_string()), path_in_share: "".to_string() }
        );
    }

    /// Tests parsing URL segments like "server/share/folder".
    #[test]
    fn test_parse_server_share_single_subpath() {
        // Case 1: "MYSERVER/MYSHARE/folder" with no default server.
        assert_eq!(
            parse_smb_path_from_url_segment("MYSERVER/MYSHARE/folder", None),
            ParsedSmbPath { server: Some("MYSERVER".to_string()), share: Some("MYSHARE".to_string()), path_in_share: "folder".to_string() }
        );
        // Case 2: "MYSERVER/MYSHARE/folder" with a different default server.
        let default_s = Some("DEFAULT".to_string());
        assert_eq!(
            parse_smb_path_from_url_segment("MYSERVER/MYSHARE/folder", default_s.as_ref()),
            ParsedSmbPath { server: Some("MYSERVER".to_string()), share: Some("MYSHARE".to_string()), path_in_share: "folder".to_string() }
        );
        // Case 3: "DEFAULT/MYSHARE/folder" where "DEFAULT" matches default server.
        assert_eq!(
            parse_smb_path_from_url_segment("DEFAULT/MYSHARE/folder", default_s.as_ref()),
            ParsedSmbPath { server: Some("DEFAULT".to_string()), share: Some("MYSHARE".to_string()), path_in_share: "folder".to_string() }
        );
        // Case 4: "THESHARE/THEFOLDER" with a default server. (Interpreted as default_server/THESHARE/THEFOLDER)
        assert_eq!(
            parse_smb_path_from_url_segment("THESHARE/THEFOLDER", default_s.as_ref()),
            ParsedSmbPath { server: Some("DEFAULT".to_string()), share: Some("THESHARE".to_string()), path_in_share: "THEFOLDER".to_string() }
        );
    }

    /// Tests parsing URL segments with multiple path components like "server/share/folder1/folder2/file.txt".
    #[test]
    fn test_parse_server_share_multiple_subpaths() {
        let path = "MYSERVER/MYSHARE/folder1/folder2/file.txt";
        let expected = ParsedSmbPath { 
            server: Some("MYSERVER".to_string()), 
            share: Some("MYSHARE".to_string()), 
            path_in_share: "folder1/folder2/file.txt".to_string() 
        };
        // Case 1: No default server.
        assert_eq!(parse_smb_path_from_url_segment(path, None), expected);

        // Case 2: With a different default server.
        let default_s = Some("DEFAULT_SERVER_NAME".to_string());
        assert_eq!(parse_smb_path_from_url_segment(path, default_s.as_ref()), expected);
        
        // Case 3: Path where the first component matches the default server.
        let path_on_default_server_share = "DEFAULT_SERVER_NAME/MYSHARE/folder1/folder2/file.txt";
         assert_eq!(
            parse_smb_path_from_url_segment(path_on_default_server_share, default_s.as_ref()), 
            ParsedSmbPath { 
                server: Some("DEFAULT_SERVER_NAME".to_string()), 
                share: Some("MYSHARE".to_string()), 
                path_in_share: "folder1/folder2/file.txt".to_string() 
            }
        );

        // Case 4: Path implies use of default server (server part omitted from URL segment).
        let path_on_default_server_share_implicit = "MYSHARE_ON_DEFAULT/folder1/folder2/file.txt";
         assert_eq!(
            parse_smb_path_from_url_segment(path_on_default_server_share_implicit, default_s.as_ref()), 
            ParsedSmbPath { 
                server: Some("DEFAULT_SERVER_NAME".to_string()), 
                share: Some("MYSHARE_ON_DEFAULT".to_string()), 
                path_in_share: "folder1/folder2/file.txt".to_string() 
            }
        );
    }

    /// Tests parsing paths with trailing slashes, ensuring they are handled correctly.
    #[test]
    fn test_parse_path_with_trailing_slashes() {
        // Single subpath with trailing slash.
        assert_eq!(
            parse_smb_path_from_url_segment("MYSERVER/MYSHARE/folder/", None),
            ParsedSmbPath { server: Some("MYSERVER".to_string()), share: Some("MYSHARE".to_string()), path_in_share: "folder".to_string() }
        );
        // Multiple subpaths with trailing slash.
         assert_eq!(
            parse_smb_path_from_url_segment("MYSERVER/MYSHARE/folder1/folder2/", None),
            ParsedSmbPath { server: Some("MYSERVER".to_string()), share: Some("MYSHARE".to_string()), path_in_share: "folder1/folder2".to_string() }
        );
    }
}
