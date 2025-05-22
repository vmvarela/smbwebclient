//! Integration tests for the web handlers and application setup.
//!
//! These tests simulate HTTP requests to the application's endpoints and verify
//! the responses, including status codes, redirection, session handling, and
//! correct rendering of templates with mock data. They use Actix's test utilities
//! and mock SMB handler functions to isolate web layer logic from actual SMB server interactions.

use actix_web::{test, web, App, HttpResponse, Responder, http::StatusCode, http::header}; // Removed HttpServer as it's not directly used for test::init_service
use actix_session::{Session, SessionMiddleware, storage::CookieSessionStore, UserSession}; // UserSession for direct session manipulation in tests
use actix_web::cookie::Key;
use tera::Tera;
use std::sync::Arc;
use std::collections::HashMap;

// Import items from the main crate.
// `crate::` refers to the root of the current crate (smb-handler-example).
use crate::config::AppConfig;
use crate::smb_handler::{SmbCreds, ShareInfo, DirectoryEntry, EntryType, AppError};
// `crate::auth` functions are not directly called by these *test handlers* but are implicitly tested via session middleware.

// --- Mock SMB Handler ---

/// Holds predefined results for mock SMB operations.
/// This allows tests to control the outcomes of SMB interactions simulated by mock functions.
#[derive(Clone)] // Clone is useful if each test or app instance needs its own copy
struct MockSmbResponses {
    /// The result to return when `mock_list_shares` is called.
    list_shares_result: Result<Vec<ShareInfo>, AppError>,
    /// The result to return when `mock_list_path` is called.
    list_path_result: Result<Vec<DirectoryEntry>, AppError>,
}

impl Default for MockSmbResponses {
    /// Provides default mock responses: successful listing of one share and one file.
    fn default() -> Self {
        MockSmbResponses {
            list_shares_result: Ok(vec![ShareInfo {
                name: "mock_share".to_string(),
                share_type: "Disk".to_string(),
                comment: "Mocked share for testing".to_string(),
            }]),
            list_path_result: Ok(vec![DirectoryEntry {
                name: "mock_file.txt".to_string(),
                entry_type: EntryType::File,
                size: 1234,
                modified: 1678886400, // Example Unix timestamp (e.g., 2023-03-15T12:00:00Z)
            }]),
        }
    }
}

// --- Mocked SMB Functions ---

/// Mock version of `smb_handler::list_shares`.
/// Returns a predefined result from `MockSmbResponses`.
async fn mock_list_shares(
    _server_address: &str, // Ignored in mock
    _creds: &SmbCreds,     // Ignored in mock
    mock_responses: &MockSmbResponses,
) -> Result<Vec<ShareInfo>, AppError> {
    log::debug!("mock_list_shares called, returning predefined result.");
    mock_responses.list_shares_result.clone() // Clone to allow multiple calls if response struct is shared
}

/// Mock version of `smb_handler::list_path`.
/// Returns a predefined result from `MockSmbResponses`.
async fn mock_list_path(
    _server_address: &str, // Ignored
    _share_name: &str,     // Ignored
    _path: &str,           // Ignored
    _creds: &SmbCreds,     // Ignored
    mock_responses: &MockSmbResponses,
) -> Result<Vec<DirectoryEntry>, AppError> {
    log::debug!("mock_list_path called, returning predefined result.");
    mock_responses.list_path_result.clone()
}


// --- Test Web Handlers (using mocks) ---
// These handlers are simplified versions of the actual web_handlers, adapted to use
// the mock SMB functions and focus on testing the web layer logic (routing, session, template rendering).

/// Test version of `LoginFormData` for form submissions in tests.
#[derive(serde::Deserialize)]
struct TestLoginFormData {
    username: String,
    password: String,
    domain: Option<String>,
    server: Option<String>,
}

/// Test handler for `GET /login`. Renders the login page.
async fn test_login_get(tera: web::Data<Tera>, config: web::Data<AppConfig>, session: Session) -> impl Responder {
    let mut ctx = tera::Context::new();
    ctx.insert("default_server", &config.default_smb_server.as_deref().unwrap_or(""));
    // Check for existing login to display user info (simplified for test)
    if let Ok(Some(creds_map)) = session.get::<HashMap<String, String>>("smb_creds_map") {
        ctx.insert("current_user_info", &creds_map);
    }
    match tera.render("login.html", &ctx) {
        Ok(rendered) => HttpResponse::Ok().content_type("text/html").body(rendered),
        Err(e) => {
            log::error!("Template error (test_login_get): {:?}", e);
            HttpResponse::InternalServerError().body(format!("Template error in test_login_get: {}", e))
        }
    }
}

/// Test handler for `POST /login`. Simulates login attempts using mock SMB functions.
async fn test_login_post(
    form: web::Form<TestLoginFormData>,
    session: Session,
    config: web::Data<AppConfig>,
    tera: web::Data<Tera>,
    mock_responses: web::Data<Arc<MockSmbResponses>>, // Injected mock responses
) -> impl Responder {
    // Determine server address similarly to actual handler
    let server_address = form.server.as_ref()
        .filter(|s| !s.is_empty())
        .or(config.default_smb_server.as_ref())
        .cloned()
        .unwrap_or_else(|| "default_mock_server.com".to_string()); // Fallback for test

    let creds = SmbCreds { // Use actual SmbCreds for call to mock
        user: form.username.clone(),
        pass: form.password.clone(),
        domain: form.domain.clone(),
    };

    // Call the mock SMB function
    match mock_list_shares(&server_address, &creds, &mock_responses).await {
        Ok(_) => { // Mocked success
            // Simulate storing simplified credentials/user info in session for test purposes
            let mut creds_map = HashMap::new();
            creds_map.insert("user".to_string(), creds.user.clone());
            creds_map.insert("server".to_string(), server_address.clone()); // Store server from this login
            session.insert("smb_creds_map", creds_map).expect("Failed to insert mock creds into session");
            session.insert("target_smb_server", server_address).expect("Failed to insert target server into session");
            HttpResponse::Found().append_header((header::LOCATION, "/browse/")).finish()
        }
        Err(AppError::AuthFailure) => { // Mocked authentication failure
            let mut ctx = tera::Context::new();
            ctx.insert("error_message", "Authentication failed.");
            ctx.insert("default_server", &server_address);
            ctx.insert("username", &form.username);
            let rendered = tera.render("login.html", &ctx).unwrap_or_else(|e| format!("Auth failed, template error: {}",e));
            HttpResponse::Unauthorized().content_type("text/html").body(rendered)
        }
        Err(e) => { // Other mocked errors
            log::error!("Mock SMB error during login test: {:?}", e);
            HttpResponse::InternalServerError().body("Mock SMB error occurred")
        }
    }
}

/// Test handler for `GET /logout`. Clears the session.
async fn test_logout(session: Session) -> impl Responder {
    session.clear(); // Clear all session data
    HttpResponse::Found().append_header((header::LOCATION, "/login")).finish()
}

/// Test handler for `GET /browse/{path:.*}`. Simulates browsing using mock SMB data.
async fn test_browse(
    path: web::Path<String>,
    session: Session,
    tera: web::Data<Tera>,
    config: web::Data<AppConfig>,
    mock_responses: web::Data<Arc<MockSmbResponses>>, // Injected mock responses
) -> impl Responder {
    let original_url_segment = path.into_inner();
    // Check for authentication (simplified for test)
    let creds_map_opt: Option<HashMap<String, String>> = session.get("smb_creds_map").unwrap_or(None);

    if creds_map_opt.is_none() && !config.allow_anonymous_browsing {
        return HttpResponse::Found().append_header((header::LOCATION, "/login")).finish();
    }

    let mut ctx = tera::Context::new();
    if let Some(ref creds_map) = creds_map_opt {
        ctx.insert("current_user_info", creds_map);
    }

    // Simplified path parsing for tests. Focus is on handler logic given a path, not exhaustive path parsing here.
    // The actual `parse_smb_path_from_url_segment` is unit-tested separately.
    let parts: Vec<&str> = original_url_segment.split('/').filter(|s| !s.is_empty()).collect();
    let (server, share, _subpath_in_share) = match parts.as_slice() {
        [] => (config.default_smb_server.clone().unwrap_or_else(|| "mock_server".to_string()), None, "".to_string()),
        [s] => (s.to_string(), None, "".to_string()), // Assumed to be server if only one part
        [s, sh] => (s.to_string(), Some(sh.to_string()), "".to_string()),
        [s, sh, p @ ..] => (s.to_string(), Some(sh.to_string()), p.join("/")),
    };
    
    ctx.insert("server_being_browsed", &server);
    ctx.insert("original_url_segment", &original_url_segment);
    ctx.insert("current_display_path", &format!("/{}/{}", server, share.as_deref().unwrap_or(""))); // Simplified display path

    // Dummy SmbCreds for calling mock functions, as actual creds aren't deeply inspected by mocks.
    let dummy_creds = SmbCreds { user: "test_user".into(), pass: "test_pass".into(), domain: None };

    if let Some(ref share_name) = share {
        // Listing path within a share
        ctx.insert("share_being_browsed", share_name);
        match mock_list_path(&server, share_name, "", &dummy_creds, &mock_responses).await {
            Ok(entries) => {
                // Convert mock DirectoryEntry to something BrowserItem-like for template
                let items_for_template: Vec<HashMap<String, String>> = entries.iter().map(|e| {
                    let mut hm = HashMap::new();
                    hm.insert("name".to_string(), e.name.clone());
                    hm.insert("entry_type".to_string(), format!("{:?}",e.entry_type)); // Assuming EntryType derives Debug and Serialize
                    hm.insert("size".to_string(), e.size.to_string());
                    hm.insert("modified".to_string(), e.modified.to_string());
                    hm.insert("is_share_item".to_string(), "false".to_string());
                    hm.insert("full_path".to_string(), e.name.clone()); // Simplified for test template compatibility
                    hm
                }).collect();
                ctx.insert("items", &items_for_template);
            }
            Err(e) => {
                log::error!("Mock list_path error: {:?}", e);
                ctx.insert("error_message", "Error listing path (mocked)");
            }
        }
    } else {
        // Listing shares on a server
        ctx.insert("is_listing_shares", &true);
        match mock_list_shares(&server, &dummy_creds, &mock_responses).await {
            Ok(shares) => {
                 let items_for_template: Vec<HashMap<String, String>> = shares.iter().map(|s| {
                    let mut hm = HashMap::new();
                    hm.insert("name".to_string(), s.name.clone());
                    hm.insert("share_type".to_string(), s.share_type.clone());
                    hm.insert("comment".to_string(), s.comment.clone());
                    hm.insert("is_share_item".to_string(), "true".to_string());
                    hm
                }).collect();
                ctx.insert("items", &items_for_template);
            }
            Err(e) => {
                log::error!("Mock list_shares error: {:?}", e);
                ctx.insert("error_message", "Error listing shares (mocked)");
            }
        }
    }
    
    // Add other necessary context variables for browser.html to prevent render errors
    // These should match the variables expected by the actual browser.html template.
    ctx.insert("path_segments", &Vec::<String>::new()); // Simplified for test
    ctx.insert("show_up_link", &false); // Simplified
    ctx.insert("parent_path_display", ""); // Simplified

    match tera.render("browser.html", &ctx) {
        Ok(rendered) => HttpResponse::Ok().content_type("text/html").body(rendered),
        Err(e) => {
            log::error!("Template error (test_browse): {:?}", e);
            HttpResponse::InternalServerError().body(format!("Template error in test_browse: {}",e))
        }
    }
}

/// Test handler for `GET /`. Redirects to `/browse/`.
async fn test_index_redirect() -> impl Responder {
    HttpResponse::Found().append_header((header::LOCATION, "/browse/")).finish()
}


// --- Test Suite ---
#[actix_web::rt::test]
async fn test_web_routes_and_handlers() {
    // Initialize Tera. Ensure templates are accessible relative to the crate root
    // where `cargo test` is typically run.
    let tera = Tera::new("../templates/**/*") // Adjusted path assuming tests run from crate root, templates are in ../templates
        .or_else(|_| Tera::new("templates/**/*")) // Fallback if CWD is already smb-handler-example
        .expect("Failed to initialize Tera for tests. Check template path.");
    
    // Generate a fixed session key for test repeatability.
    let test_key = Key::generate(); // Generates a 64-byte key suitable for AES-128-GCM.

    // --- Test: GET /login (renders login page) ---
    let mock_responses_login_get = Arc::new(MockSmbResponses::default());
    let app_login_get = test::init_service(
        App::new()
            .app_data(web::Data::new(AppConfig::load())) // Use default AppConfig
            .app_data(web::Data::new(tera.clone()))      // Share Tera instance
            .app_data(web::Data::new(mock_responses_login_get.clone())) // Share mock responses
            .wrap(SessionMiddleware::new(CookieSessionStore::default(), test_key.clone())) // Session middleware
            .route("/login", web::get().to(test_login_get)) // Route to test handler
    ).await;
    let req_login_get = test::TestRequest::get().uri("/login").to_request();
    let resp_login_get = test::call_service(&app_login_get, req_login_get).await;
    assert_eq!(resp_login_get.status(), StatusCode::OK, "GET /login should return 200 OK.");
    let body_login_get = test::read_body(resp_login_get).await;
    assert!(String::from_utf8_lossy(&body_login_get).contains("Login to SMB Server"), "Login page content missing.");


    // --- Test: POST /login (Authentication Failure) ---
    // Configure mock to return authentication error.
    let mock_responses_login_fail = Arc::new(MockSmbResponses {
        list_shares_result: Err(AppError::AuthFailure), // Simulate auth failure
        ..Default::default()
    });
     let app_login_fail = test::init_service(
        App::new()
            .app_data(web::Data::new(AppConfig::load()))
            .app_data(web::Data::new(tera.clone()))
            .app_data(web::Data::new(mock_responses_login_fail.clone()))
            .wrap(SessionMiddleware::new(CookieSessionStore::default(), test_key.clone()))
            .route("/login", web::post().to(test_login_post))
    ).await;
    let req_login_fail = test::TestRequest::post()
        .uri("/login")
        .set_form(&TestLoginFormData { // Submit login form data
            username: "user".to_string(), password: "wrong_password".to_string(), domain: None, server: Some("testserver".to_string())
        })
        .to_request();
    let resp_login_fail = test::call_service(&app_login_fail, req_login_fail).await;
    assert_eq!(resp_login_fail.status(), StatusCode::UNAUTHORIZED, "POST /login with bad creds should be 401 Unauthorized.");
    let body_login_fail = test::read_body(resp_login_fail).await;
    assert!(String::from_utf8_lossy(&body_login_fail).contains("Authentication failed"), "Error message for auth failure missing.");


    // --- Test: POST /login (Authentication Success) ---
    let mock_responses_login_ok = Arc::new(MockSmbResponses::default()); // Default mock is successful share listing.
    let app_login_ok = test::init_service(
        App::new()
            .app_data(web::Data::new(AppConfig::load()))
            .app_data(web::Data::new(tera.clone()))
            .app_data(web::Data::new(mock_responses_login_ok.clone()))
            .wrap(SessionMiddleware::new(CookieSessionStore::default(), test_key.clone()))
            .route("/login", web::post().to(test_login_post))
    ).await;
    let req_login_ok = test::TestRequest::post()
        .uri("/login")
        .set_form(&TestLoginFormData {
            username: "user".to_string(), password: "correct_password".to_string(), domain: None, server: Some("testserver".to_string())
        })
        .to_request();
    let resp_login_ok = test::call_service(&app_login_ok, req_login_ok).await;
    assert_eq!(resp_login_ok.status(), StatusCode::FOUND, "Successful POST /login should redirect (302 Found).");
    // Check redirection location.
    assert_eq!(resp_login_ok.headers().get(header::LOCATION).unwrap(), "/browse/", "Redirect location should be /browse/.");
    // Check if a session cookie was set.
    assert!(
        resp_login_ok.headers().get(header::SET_COOKIE).map_or(false, |h| h.to_str().unwrap_or("").contains("smb-browser-session")),
        "Session cookie 'smb-browser-session' should be set on successful login."
    );


    // --- Test: GET /logout ---
    let mock_responses_logout = Arc::new(MockSmbResponses::default()); // Mock responses not critical for logout logic itself.
    let app_logout = test::init_service(
        App::new()
            .app_data(web::Data::new(AppConfig::load()))
            .app_data(web::Data::new(tera.clone()))
            .app_data(web::Data::new(mock_responses_logout.clone()))
            .wrap(SessionMiddleware::new(CookieSessionStore::default(), test_key.clone()))
            .route("/logout", web::get().to(test_logout))
    ).await;
    let req_logout = test::TestRequest::get().uri("/logout").to_request();
    let resp_logout = test::call_service(&app_logout, req_logout).await;
    assert_eq!(resp_logout.status(), StatusCode::FOUND, "GET /logout should redirect.");
    assert_eq!(resp_logout.headers().get(header::LOCATION).unwrap(), "/login", "Logout should redirect to /login.");
    // Optional: Could also check that the session cookie is marked for deletion if the middleware does that.


    // --- Test: GET /browse/ (Not Authenticated, Anonymous Browsing Disabled) ---
    let mut cfg_no_anon = AppConfig::load(); // Load default config
    cfg_no_anon.allow_anonymous_browsing = false; // Explicitly disable anonymous browsing
    let mock_responses_browse_no_auth = Arc::new(MockSmbResponses::default());
    let app_browse_no_auth = test::init_service(
        App::new()
            .app_data(web::Data::new(cfg_no_anon)) // Use the modified config
            .app_data(web::Data::new(tera.clone()))
            .app_data(web::Data::new(mock_responses_browse_no_auth.clone()))
            .wrap(SessionMiddleware::new(CookieSessionStore::default(), test_key.clone()))
            .route("/browse/{path:.*}", web::get().to(test_browse)) // Route to test browse handler
    ).await;
    let req_browse_no_auth = test::TestRequest::get().uri("/browse/").to_request(); // No session data
    let resp_browse_no_auth = test::call_service(&app_browse_no_auth, req_browse_no_auth).await;
    assert_eq!(resp_browse_no_auth.status(), StatusCode::FOUND, "GET /browse/ without auth (anon disabled) should redirect.");
    assert_eq!(resp_browse_no_auth.headers().get(header::LOCATION).unwrap(), "/login", "Should redirect to /login if not authenticated and anon browsing is off.");


    // --- Test: GET /browse/ (Authenticated, Listing Shares on a server) ---
    // Mock specific shares for this test.
    let mock_responses_browse_shares = Arc::new(MockSmbResponses {
        list_shares_result: Ok(vec![
            ShareInfo { name: "share1_mock".into(), share_type: "Disk".into(), comment: "Mocked Share One".into() },
            ShareInfo { name: "share2_mock".into(), share_type: "Disk".into(), comment: "Mocked Share Two".into() },
        ]),
        ..Default::default()
    });
    let app_browse_shares = test::init_service(
        App::new()
            .app_data(web::Data::new(AppConfig::load())) // Default config
            .app_data(web::Data::new(tera.clone()))
            .app_data(web::Data::new(mock_responses_browse_shares.clone()))
            .wrap(SessionMiddleware::new(CookieSessionStore::default(), test_key.clone()))
            .route("/browse/{path:.*}", web::get().to(test_browse))
    ).await;
    
    // Create a request with an active session (simulating a logged-in user).
    let mut session_data = HashMap::new();
    session_data.insert("user".to_string(), "test_user".to_string());
    session_data.insert("server".to_string(), "mockserver".to_string()); // Server they logged into
    let test_session = test::TestRequest::get().session(); // Get a session object for the request
    test_session.insert("smb_creds_map", session_data).unwrap(); // Store mock "credentials"
    test_session.insert("target_smb_server", "mockserver").unwrap(); // Store target server

    let req_browse_shares = test::TestRequest::get()
        .uri("/browse/mockserver") // Path specifies the server to browse shares on
        .cookie(test_session.state_cookie().unwrap()) // Attach the session cookie to the request
        .to_request();
        
    let resp_browse_shares = test::call_service(&app_browse_shares, req_browse_shares).await;
    assert_eq!(resp_browse_shares.status(), StatusCode::OK, "GET /browse/mockserver (authenticated) should return 200 OK.");
    let body_browse_shares = test::read_body(resp_browse_shares).await;
    let body_str = String::from_utf8_lossy(&body_browse_shares);
    // Check if the mock share names are present in the rendered HTML.
    assert!(body_str.contains("share1_mock"), "Missing mock share 'share1_mock' in output.");
    assert!(body_str.contains("share2_mock"), "Missing mock share 'share2_mock' in output.");
    

    // --- Test: GET /browse/server/share (Authenticated, Listing Path Contents) ---
    // Mock specific directory entries for this test.
    let mock_responses_browse_path = Arc::new(MockSmbResponses {
        list_path_result: Ok(vec![
            DirectoryEntry { name: "file_A.txt".into(), entry_type: EntryType::File, size: 1024, modified: 0 },
            DirectoryEntry { name: "folder_B".into(), entry_type: EntryType::Directory, size: 0, modified: 0 },
        ]),
        ..Default::default()
    });
    let app_browse_path = test::init_service(
        App::new()
            .app_data(web::Data::new(AppConfig::load()))
            .app_data(web::Data::new(tera.clone()))
            .app_data(web::Data::new(mock_responses_browse_path.clone()))
            .wrap(SessionMiddleware::new(CookieSessionStore::default(), test_key.clone()))
            .route("/browse/{path:.*}", web::get().to(test_browse))
    ).await;

    // Re-use session from previous authenticated test or create a new one.
    let req_browse_path = test::TestRequest::get()
        .uri("/browse/mockserver/mock_share_name") // Path specifies server and share
        .cookie(test_session.state_cookie().unwrap()) // Use same session cookie
        .to_request();
        
    let resp_browse_path = test::call_service(&app_browse_path, req_browse_path).await;
    assert_eq!(resp_browse_path.status(), StatusCode::OK, "GET /browse/server/share (authenticated) should be 200 OK.");
    let body_browse_path = test::read_body(resp_browse_path).await;
    let body_str_path = String::from_utf8_lossy(&body_browse_path);
    // Check if mock file/folder names are present.
    assert!(body_str_path.contains("file_A.txt"), "Missing mock file 'file_A.txt'.");
    assert!(body_str_path.contains("folder_B"), "Missing mock folder 'folder_B'.");

    // --- Test: GET / (Index redirect) ---
    let mock_responses_index = Arc::new(MockSmbResponses::default()); // Mock responses not critical for redirect.
     let app_index = test::init_service(
        App::new()
            // No app_data needed beyond what SessionMiddleware might require if it were more complex.
            .wrap(SessionMiddleware::new(CookieSessionStore::default(), test_key.clone()))
            .route("/", web::get().to(test_index_redirect)) // Route to the test redirect handler
    ).await;
    let req_index = test::TestRequest::get().uri("/").to_request();
    let resp_index = test::call_service(&app_index, req_index).await;
    assert_eq!(resp_index.status(), StatusCode::FOUND, "GET / should redirect.");
    assert_eq!(resp_index.headers().get(header::LOCATION).unwrap(), "/browse/", "Index should redirect to /browse/.");
}
