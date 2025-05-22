//! Manages SMB credentials within the Actix session.
//!
//! This module provides helper functions to store, retrieve, and clear
//! `SmbCreds` from the user's session. It acts as an interface between
//! web handlers and the session state concerning SMB authentication.

use actix_session::Session;
use crate::smb_handler::SmbCreds; // Assuming SmbCreds is in smb_handler
// SmbCreds needs to derive Serialize and Deserialize for session storage.
// This should be handled in smb_handler.rs where SmbCreds is defined.

/// Key used to store `SmbCreds` in the Actix session.
const SMB_CREDS_KEY: &str = "smb_creds";

/// Retrieves SMB credentials from the current session.
///
/// # Arguments
/// * `session`: A reference to the Actix `Session` object.
///
/// # Returns
/// An `Option<SmbCreds>` which is `Some(SmbCreds)` if credentials are found
/// and successfully deserialized, or `None` otherwise (e.g., not logged in, or deserialization error).
pub fn get_smb_credentials(session: &Session) -> Option<SmbCreds> {
    match session.get(SMB_CREDS_KEY) {
        Ok(creds_opt) => creds_opt, // creds_opt is Option<SmbCreds>
        Err(e) => {
            // Log error if there was an issue trying to get data from the session store
            // (e.g., deserialization error if the data is corrupt or format changed).
            log::error!("Error getting SMB credentials from session: {:?}", e);
            None
        }
    }
}

/// Stores SMB credentials into the current session.
///
/// # Arguments
/// * `session`: A reference to the Actix `Session` object.
/// * `creds`: A reference to the `SmbCreds` to be stored.
///
/// # Returns
/// `Ok(())` if credentials were successfully inserted, or an `actix_session::SessionInsertError`
/// if there was an error during serialization or session storage.
pub fn set_smb_credentials(session: &Session, creds: &SmbCreds) -> Result<(), actix_session::SessionInsertError> {
    // This will serialize SmbCreds into a format storable by the session backend (e.g., JSON).
    session.insert(SMB_CREDS_KEY, creds)
}

/// Clears any stored SMB credentials from the current session.
///
/// This is typically called during logout.
///
/// # Arguments
/// * `session`: A reference to the Actix `Session` object.
pub fn clear_smb_credentials(session: &Session) {
    // Removes the key-value pair from the session.
    session.remove(SMB_CREDS_KEY);
}
