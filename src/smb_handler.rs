//! Provides core SMB (Server Message Block) protocol functionalities.
//!
//! This module encapsulates the logic for interacting with SMB shares,
//! including listing shares and directory contents, file operations (upload, download, delete),
//! and directory management. It uses the `smbclient-rs` crate to perform
//! these operations and defines common data structures for credentials,
//! share information, and directory entries. It also includes a custom `AppError`
//! type for handling errors specific to SMB operations or related I/O issues.

use smbclient_rs::{Share, SmbClient, SmbDirent, SmbFile, SmbMode, SmbOpenOptions, SmbStat};
use std::io;
use tokio::io::{AsyncRead, AsyncWriteExt};
use futures::stream::{Stream, StreamExt};

// --- Data Structures ---

/// Represents credentials for authenticating with an SMB server.
///
/// All fields are public to allow easy construction and access.
/// The `serde::Serialize` and `serde::Deserialize` traits are derived to allow
/// these credentials to be stored in a user session.
#[derive(Clone, Debug, serde::Serialize, serde::Deserialize)]
pub struct SmbCreds {
    /// The username for SMB authentication.
    pub user: String,
    /// The password for SMB authentication.
    pub pass: String,
    /// Optional domain or workgroup for SMB authentication.
    /// `smbclient-rs` often requires a domain; "WORKGROUP" can be a common default if none is specified.
    pub domain: Option<String>,
}

/// Information about a specific SMB share.
///
/// Derived `serde::Serialize` and `serde::Deserialize` for potential future use,
/// though currently primarily used for displaying share details.
#[derive(Debug, serde::Serialize, serde::Deserialize)]
pub struct ShareInfo {
    /// The name of the share (e.g., "documents", "public").
    pub name: String,
    /// The type of the share, as reported by the server (e.g., "Disk", "Printer").
    pub share_type: String,
    /// A comment or description associated with the share.
    pub comment: String,
}

/// Enum representing the type of a directory entry (File or Directory).
///
/// Derived `serde::Serialize` and `serde::Deserialize` for consistent data handling,
/// especially when these entries are part of API responses or web templates.
#[derive(Debug, serde::Serialize, serde::Deserialize, PartialEq, Eq, Clone, Copy)] // Added more common derives
pub enum EntryType {
    /// Indicates the entry is a file.
    File,
    /// Indicates the entry is a directory.
    Directory,
}

/// Detailed information about an entry (file or directory) within an SMB share.
///
/// Derived `serde::Serialize` and `serde::Deserialize` for data interchange.
#[derive(Debug, serde::Serialize, serde::Deserialize)]
pub struct DirectoryEntry {
    /// The name of the file or directory.
    pub name: String,
    /// The type of the entry (File or Directory).
    pub entry_type: EntryType,
    /// The size of the file in bytes. For directories, this may be 0 or a system-dependent value.
    pub size: u64,
    /// The last modification time of the entry, represented as a Unix timestamp (seconds since epoch).
    pub modified: i64,
}

// --- Custom Error Type ---

/// Represents errors that can occur during SMB operations or related I/O.
///
/// This enum centralizes error handling for the `smb_handler` module,
/// converting errors from `smbclient-rs` and `std::io` into a consistent format.
#[derive(Debug, thiserror::Error)]
pub enum AppError {
    /// An error originating from the underlying `smbclient-rs` library.
    #[error("SMB operation failed: {0}")]
    SmbError(#[from] smbclient_rs::Error),
    /// An I/O error, typically related to network communication or file streaming.
    #[error("IO error: {0}")]
    IoError(#[from] io::Error),
    /// Indicates that a requested resource (file, directory, or share) was not found.
    #[error("Not found: {0}")]
    NotFound(String),
    /// Indicates that an operation is not supported or not implemented.
    #[error("Unsupported operation: {0}")]
    Unsupported(String),
    /// Indicates that a provided path string is invalid or malformed.
    #[error("Invalid path: {0}")]
    InvalidPath(String),
    /// An error occurred during a recursive directory deletion. The string argument provides context.
    #[error("Recursive deletion failed for: {0}")]
    RecursiveDeleteError(String),
    /// Specifically indicates an authentication failure (e.g., wrong username/password).
    #[error("Authentication failed")]
    AuthFailure,
    /// A catch-all for other types of errors, with a descriptive message.
    #[error("Other error: {0}")]
    Other(String),
}

// --- Helper Functions ---

/// Constructs an SMB URL string.
///
/// # Arguments
/// * `server_address`: The hostname or IP address of the SMB server.
/// * `share_name`: Optional name of the share. If present, it's appended to the server address.
/// * `path`: Optional path within the share. If present, it's appended after the share name.
///
/// # Returns
/// A formatted SMB URL string (e.g., "smb://server/share/path").
fn build_smb_url(server_address: &str, share_name: Option<&str>, path: Option<&str>) -> String {
    let mut url = format!("smb://{}", server_address);
    if let Some(s_name) = share_name {
        url.push('/');
        url.push_str(s_name);
    }
    if let Some(p) = path {
        // Ensure a leading slash if a share is present and the path doesn't already have one.
        if !p.starts_with('/') && share_name.is_some() {
            url.push('/');
        } else if share_name.is_none() && !p.starts_with('/') {
            // If no share, but path is given, ensure path starts with a slash if it's meant to be absolute from server root (less common for SMB URLs)
            // However, smbclient-rs usually expects paths relative to a share.
            // This logic primarily ensures correct joining.
             url.push('/');
        }
        url.push_str(p.trim_start_matches('/')); // Trim leading slashes from path part to avoid smb://server//path
    }
    url
}

/// Creates and configures an `SmbClient` instance with the provided credentials.
///
/// # Arguments
/// * `creds`: A reference to `SmbCreds` containing the username, password, and optional domain.
///
/// # Returns
/// A configured `SmbClient` instance.
///
/// # Panics
/// This function might panic if `SmbClient::new()` fails, though this is unlikely with default settings.
fn create_smb_client(creds: &SmbCreds) -> SmbClient {
    let mut client = SmbClient::new().expect("Failed to create SmbClient with default config.");
    // `smbclient-rs` requires a domain; "WORKGROUP" is a common default if no specific domain is provided.
    client.set_credentials(
        creds.domain.as_deref().unwrap_or("WORKGROUP"),
        &creds.user,
        &creds.pass,
    );
    client
}

// --- Public SMB Operation Functions ---

/// Lists all available shares on a given SMB server.
///
/// # Arguments
/// * `server_address`: The hostname or IP address of the SMB server.
/// * `creds`: SMB credentials for authentication.
///
/// # Returns
/// A `Result` containing a `Vec<ShareInfo>` on success, or an `AppError` on failure.
/// `AppError::AuthFailure` is returned for authentication issues.
pub async fn list_shares(server_address: &str, creds: &SmbCreds) -> Result<Vec<ShareInfo>, AppError> {
    let client = create_smb_client(creds);
    // The URL for listing shares is just the server address.
    let url = build_smb_url(server_address, None, None);
    
    log::info!("Listing shares for URL: {}", url);

    match client.list_shares(&url).await {
        Ok(shares) => {
            // Map from smbclient_rs::Share to our ShareInfo struct.
            let share_infos = shares
                .into_iter()
                .map(|s| ShareInfo {
                    name: s.name().to_string_lossy().into_owned(),
                    share_type: s.share_type().to_string_lossy().into_owned(),
                    comment: s.comment().to_string_lossy().into_owned(),
                })
                .collect();
            Ok(share_infos)
        }
        Err(e) => {
            log::error!("Failed to list shares for {}: {:?}", server_address, e);
            // Map specific smbclient_rs errors to AppError variants.
            if matches!(e, smbclient_rs::Error::Auth(_)) {
                Err(AppError::AuthFailure)
            } else {
                Err(AppError::from(e)) // General conversion for other SMB errors.
            }
        }
    }
}

/// Lists the contents (files and directories) of a specific path within an SMB share.
///
/// # Arguments
/// * `server_address`: The hostname or IP address of the SMB server.
/// * `share_name`: The name of the share to browse.
/// * `path`: The path within the share to list. An empty string or "/" usually refers to the root of the share.
/// * `creds`: SMB credentials for authentication.
///
/// # Returns
/// A `Result` containing a `Vec<DirectoryEntry>` on success, or an `AppError` on failure.
pub async fn list_path(
    server_address: &str,
    share_name: &str,
    path: &str,
    creds: &SmbCreds,
) -> Result<Vec<DirectoryEntry>, AppError> {
    let client = create_smb_client(creds);
    // Construct the full SMB URL for the path to be listed.
    let smb_path_url = build_smb_url(server_address, Some(share_name), Some(path));
    
    log::info!("Listing path: {}", smb_path_url);

    // Open the directory at the specified path.
    let dir = client.opendir(&smb_path_url).await?;
    let mut entries = Vec::new();

    // `readdir` returns a stream of directory entries. Iterate through them.
    let mut dirent_stream = dir.readdir().await?;
    while let Some(entry_result) = dirent_stream.next().await {
        match entry_result {
            Ok(smb_dirent) => {
                let entry_name = smb_dirent.name().to_string_lossy().into_owned();
                // Skip "." (current directory) and ".." (parent directory) entries.
                if entry_name == "." || entry_name == ".." {
                    continue;
                }
                
                // To get detailed info like size and mtime, we need to stat each entry.
                // Construct the full path for stat.
                let stat_path_url = build_smb_url(server_address, Some(share_name), Some(&format!("{}/{}", path.trim_matches('/'), entry_name)));
                let stat_info = client.stat(&stat_path_url).await;

                match stat_info {
                    Ok(s) => {
                        entries.push(DirectoryEntry {
                            name: entry_name,
                            entry_type: if s.is_dir() { EntryType::Directory } else { EntryType::File },
                            size: s.size(),
                            modified: s.mtime(), // SmbStat provides mtime as i64 (Unix timestamp).
                        });
                    }
                    Err(e) => {
                        // If stat fails for an entry, log it and skip, or decide to fail the whole operation.
                        // Current behavior: log and skip the problematic entry.
                        log::warn!("Could not stat entry {} (resolved from {}/{}): {:?}", stat_path_url, smb_path_url, entry_name, e);
                    }
                }
            }
            Err(e) => {
                log::error!("Error reading directory entry in {}: {:?}", smb_path_url, e);
                return Err(AppError::from(e)); // If reading the stream itself fails, propagate the error.
            }
        }
    }
    Ok(entries)
}


/// Retrieves a readable stream for a file on an SMB share.
///
/// # Arguments
/// * `server_address`: Server hostname or IP.
/// * `share_name`: Name of the share.
/// * `file_path`: Path to the file within the share.
/// * `creds`: SMB credentials.
///
/// # Returns
/// A `Result` containing a type implementing `AsyncRead + Send` (representing the file stream)
/// on success, or an `AppError` on failure. `AppError::NotFound` is returned if the file doesn't exist.
pub async fn get_file_stream(
    server_address: &str,
    share_name: &str,
    file_path: &str,
    creds: &SmbCreds,
) -> Result<impl AsyncRead + Send, AppError> {
    let client = create_smb_client(creds);
    let full_file_path_url = build_smb_url(server_address, Some(share_name), Some(file_path));
    log::info!("Getting file stream for: {}", full_file_path_url);

    // Open the file in read mode.
    let open_options = SmbOpenOptions::new().mode(SmbMode::READ);
    match client.open_with_options(&full_file_path_url, open_options).await {
        Ok(smb_file) => Ok(smb_file), // SmbFile from smbclient-rs implements AsyncRead.
        Err(e) => {
            log::error!("Failed to open file {} for reading: {:?}", full_file_path_url, e);
            if matches!(e, smbclient_rs::Error::NotFound(_)) {
                Err(AppError::NotFound(full_file_path_url))
            } else {
                Err(AppError::from(e))
            }
        }
    }
}

/// Uploads data from a stream to a file on an SMB share.
///
/// If the file exists, it will be overwritten. If it doesn't exist, it will be created.
///
/// # Arguments
/// * `server_address`: Server hostname or IP.
/// * `share_name`: Name of the share.
/// * `path`: The remote directory path within the share where the file should be uploaded.
/// * `file_name`: The name for the new or existing file on the share.
/// * `data_stream`: An `AsyncRead` stream providing the data to upload.
/// * `_file_size`: The size of the file. `smbclient-rs` might not strictly need this for streaming uploads,
///                 but it's often good practice for protocols that support pre-allocation. (Currently unused).
/// * `creds`: SMB credentials.
///
/// # Returns
/// `Ok(())` on successful upload, or an `AppError` on failure.
pub async fn upload_file(
    server_address: &str,
    share_name: &str,
    path: &str, 
    file_name: &str,
    mut data_stream: impl AsyncRead + Unpin + Send, // `Unpin` is required for `tokio::io::copy`.
    _file_size: u64, 
    creds: &SmbCreds,
) -> Result<(), AppError> {
    let client = create_smb_client(creds);
    let remote_file_full_path = format!("{}/{}", path.trim_matches('/'), file_name).trim_start_matches('/').to_string();
    let full_file_path_url = build_smb_url(server_address, Some(share_name), Some(&remote_file_full_path));
    log::info!("Uploading file to: {}", full_file_path_url);

    // Open the file in write mode, create if not exists, truncate if exists.
    let open_options = SmbOpenOptions::new().mode(SmbMode::WRITE).create(true).truncate(true);
    let mut remote_file = client.open_with_options(&full_file_path_url, open_options).await?;
    
    // Use tokio::io::copy for efficient asynchronous streaming from data_stream to remote_file.
    tokio::io::copy(&mut data_stream, &mut remote_file).await?;
    remote_file.flush().await?; // Ensure all buffered data is written to the remote file.
    // SmbFile is closed on drop, which happens when remote_file goes out of scope.

    Ok(())
}

/// Creates a new directory on an SMB share.
///
/// # Arguments
/// * `server_address`: Server hostname or IP.
/// * `share_name`: Name of the share.
/// * `path`: The parent path within the share where the new directory should be created.
/// * `dir_name`: The name of the new directory.
/// * `creds`: SMB credentials.
///
/// # Returns
/// `Ok(())` on successful creation, or an `AppError` on failure.
pub async fn create_directory(
    server_address: &str,
    share_name: &str,
    path: &str, 
    dir_name: &str,
    creds: &SmbCreds,
) -> Result<(), AppError> {
    let client = create_smb_client(creds);
    let new_dir_full_path = format!("{}/{}", path.trim_matches('/'), dir_name).trim_start_matches('/').to_string();
    let full_dir_path_url = build_smb_url(server_address, Some(share_name), Some(&new_dir_full_path));
    log::info!("Creating directory: {}", full_dir_path_url);

    client.mkdir(&full_dir_path_url).await?;
    Ok(())
}

/// Deletes a file from an SMB share.
///
/// # Arguments
/// * `server_address`: Server hostname or IP.
/// * `share_name`: Name of the share.
/// * `file_path`: Full path to the file within the share.
/// * `creds`: SMB credentials.
///
/// # Returns
/// `Ok(())` on successful deletion, or an `AppError` on failure.
pub async fn delete_file(
    server_address: &str,
    share_name: &str,
    file_path: &str,
    creds: &SmbCreds,
) -> Result<(), AppError> {
    let client = create_smb_client(creds);
    let full_file_path_url = build_smb_url(server_address, Some(share_name), Some(file_path));
    log::info!("Deleting file: {}", full_file_path_url);

    client.unlink(&full_file_path_url).await?; // `unlink` is typically used for deleting files.
    Ok(())
}

/// Deletes a directory from an SMB share.
///
/// # Arguments
/// * `server_address`: Server hostname or IP.
/// * `share_name`: Name of the share.
/// * `dir_path`: Full path to the directory within the share.
/// * `recursive`: If `true`, recursively deletes all contents of the directory first.
///                If `false`, the operation will fail if the directory is not empty.
/// * `creds`: SMB credentials.
///
/// # Returns
/// `Ok(())` on successful deletion, or an `AppError` on failure.
/// `AppError::RecursiveDeleteError` may be returned if `recursive` is true and an error occurs
/// during deletion of an inner item.
pub async fn delete_directory(
    server_address: &str,
    share_name: &str,
    dir_path: &str, 
    recursive: bool,
    creds: &SmbCreds,
) -> Result<(), AppError> {
    let client = create_smb_client(creds);
    let full_dir_smb_url = build_smb_url(server_address, Some(share_name), Some(dir_path));

    log::info!("Deleting directory: {} (recursive: {})", full_dir_smb_url, recursive);

    if recursive {
        // `smbclient-rs` rmdir does not support recursive deletion directly.
        // We need to list and delete contents first.
        // Note: `dir_path` here is relative to the share.
        let entries = list_path(server_address, share_name, dir_path, creds).await?;
        for entry in entries {
            // Construct the full path of the entry relative to the share.
            let entry_path_in_share = format!("{}/{}", dir_path.trim_matches('/'), entry.name).trim_start_matches('/').to_string();
            match entry.entry_type {
                EntryType::File => {
                    log::debug!("Deleting file: {}/{}", full_dir_smb_url, entry.name);
                    // Call our `delete_file` function for this entry.
                    delete_file(server_address, share_name, &entry_path_in_share, creds).await.map_err(|e| {
                        log::error!("Failed to delete file {} during recursive delete of {}: {:?}", entry_path_in_share, dir_path, e);
                        AppError::RecursiveDeleteError(entry_path_in_share.clone())
                    })?;
                }
                EntryType::Directory => {
                    log::debug!("Recursively deleting directory: {}/{}", full_dir_smb_url, entry.name);
                    // Recursively call `delete_directory` for subdirectories.
                    delete_directory(server_address, share_name, &entry_path_in_share, true, creds).await.map_err(|e| {
                        log::error!("Failed to delete subdirectory {} during recursive delete of {}: {:?}", entry_path_in_share, dir_path, e);
                        AppError::RecursiveDeleteError(entry_path_in_share.clone())
                    })?;
                }
            }
        }
    }

    // After deleting contents (if recursive), or if not recursive, delete the directory itself.
    client.rmdir(&full_dir_smb_url).await.map_err(|e| {
        log::error!("Failed to delete directory {}: {:?}", full_dir_smb_url, e);
        // Map specific error types.
        if matches!(e, smbclient_rs::Error::NotFound(_)) {
            AppError::NotFound(full_dir_smb_url.clone())
        } else if matches!(e, smbclient_rs::Error::DirectoryNotEmpty(_)) && !recursive {
             AppError::Other(format!("Directory {} not empty and recursive deletion was not requested.", full_dir_smb_url))
        }
        else {
            AppError::from(e)
        }
    })?;
    Ok(())
}

/// Renames or moves an item (file or directory) on an SMB share.
///
/// Note: Behavior for moving across different directories might vary by server implementation.
/// This function primarily handles renaming within the same parent directory.
///
/// # Arguments
/// * `server_address`: Server hostname or IP.
/// * `share_name`: Name of the share.
/// * `path`: The parent path of the item to be renamed/moved.
/// * `old_name`: The current name of the item.
/// * `new_name`: The new name for the item.
/// * `creds`: SMB credentials.
///
/// # Returns
/// `Ok(())` on successful rename/move, or an `AppError` on failure.
pub async fn rename_item(
    server_address: &str,
    share_name: &str,
    path: &str, // Parent path of the item
    old_name: &str,
    new_name: &str,
    creds: &SmbCreds,
) -> Result<(), AppError> {
    let client = create_smb_client(creds);
    
    // Construct full SMB URLs for the old and new paths.
    let old_item_path_in_share = format!("{}/{}", path.trim_matches('/'), old_name).trim_start_matches('/').to_string();
    let old_full_url = build_smb_url(server_address, Some(share_name), Some(&old_item_path_in_share));

    let new_item_path_in_share = format!("{}/{}", path.trim_matches('/'), new_name).trim_start_matches('/').to_string();
    let new_full_url = build_smb_url(server_address, Some(share_name), Some(&new_item_path_in_share));

    log::info!("Renaming item from {} to {}", old_full_url, new_full_url);

    client.rename(&old_full_url, &new_full_url).await?;
    Ok(())
}


#[cfg(test)]
mod tests {
    // Basic compilation tests or simple unit tests for helper functions can go here.
    // Integration tests requiring an SMB server are more complex and typically external.
    use super::*;

    #[test]
    fn test_build_smb_url_server_only() {
        assert_eq!(build_smb_url("myserver", None, None), "smb://myserver");
    }

    #[test]
    fn test_build_smb_url_server_share() {
        assert_eq!(build_smb_url("myserver", Some("SHARE"), None), "smb://myserver/SHARE");
    }

    #[test]
    fn test_build_smb_url_server_share_path() {
        // Path without leading slash, should be appended correctly.
        assert_eq!(build_smb_url("myserver", Some("SHARE"), Some("folder/file.txt")), "smb://myserver/SHARE/folder/file.txt");
    }

    #[test]
    fn test_build_smb_url_server_share_root_path() {
        // Path with leading slash, should be handled to avoid double slashes.
        assert_eq!(build_smb_url("myserver", Some("SHARE"), Some("/folder/file.txt")), "smb://myserver/SHARE/folder/file.txt");
    }
     #[test]
    fn test_build_smb_url_server_share_empty_path() {
        assert_eq!(build_smb_url("myserver", Some("SHARE"), Some("")), "smb://myserver/SHARE/");
        assert_eq!(build_smb_url("myserver", Some("SHARE"), Some("/")), "smb://myserver/SHARE/");
    }

    #[test]
    fn test_build_smb_url_server_path_no_share() {
        // This case is less common for typical SMB shares but tests path joining logic.
        assert_eq!(build_smb_url("myserver", None, Some("some/path")), "smb://myserver/some/path");
        assert_eq!(build_smb_url("myserver", None, Some("/other/path")), "smb://myserver/other/path");
    }
}
