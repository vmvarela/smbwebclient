import React, { useState, useEffect } from 'react';
import { listFiles, downloadFile, uploadFile, createFolder, renameFile, deleteFile } from '../api';

function FileManager({ onLogout }) {
  const [files, setFiles] = useState([]);
  const [currentPath, setCurrentPath] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [newFolderName, setNewFolderName] = useState('');
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    loadFiles(currentPath);
  }, [currentPath]);

  const loadFiles = async (path) => {
    setLoading(true);
    setError('');
    try {
      const response = await listFiles(path);
      setFiles(response.data);
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to load files');
    } finally {
      setLoading(false);
    }
  };

  const handleNavigate = (fileName) => {
    const newPath = currentPath ? `${currentPath}\\${fileName}` : fileName;
    setCurrentPath(newPath);
  };

  const handleUp = () => {
    if (!currentPath) return;
    const parts = currentPath.split('\\');
    parts.pop();
    setCurrentPath(parts.join('\\'));
  };

  const handleDownload = async (fileName) => {
    const path = currentPath ? `${currentPath}\\${fileName}` : fileName;
    try {
      const response = await downloadFile(path);
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', fileName);
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (err) {
      alert('Download failed');
    }
  };

  const handleUpload = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('path', currentPath);

    setUploading(true);
    try {
      await uploadFile(formData);
      loadFiles(currentPath);
    } catch (err) {
      alert('Upload failed');
    } finally {
      setUploading(false);
      e.target.value = null; // Reset input
    }
  };

  const handleCreateFolder = async () => {
    if (!newFolderName) return;
    const path = currentPath ? `${currentPath}\\${newFolderName}` : newFolderName;
    try {
      await createFolder(path);
      setNewFolderName('');
      loadFiles(currentPath);
    } catch (err) {
      alert('Failed to create folder: ' + (err.response?.data?.error || err.message));
    }
  };

  const handleDelete = async (fileName, isDirectory) => {
    if (!window.confirm(`Are you sure you want to delete ${fileName}?`)) return;
    const path = currentPath ? `${currentPath}\\${fileName}` : fileName;
    try {
      await deleteFile(path, isDirectory);
      loadFiles(currentPath);
    } catch (err) {
      alert('Failed to delete: ' + (err.response?.data?.error || err.message));
    }
  };

  const handleRename = async (fileName) => {
      const newName = prompt("Enter new name:", fileName);
      if (!newName || newName === fileName) return;

      const oldPath = currentPath ? `${currentPath}\\${fileName}` : fileName;
      const newPath = currentPath ? `${currentPath}\\${newName}` : newName;

      try {
          await renameFile(oldPath, newPath);
          loadFiles(currentPath);
      } catch (err) {
          alert('Failed to rename: ' + (err.response?.data?.error || err.message));
      }
  };

  return (
    <div className="container">
      <div className="card">
        <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
            <h3>File Manager</h3>
            <button onClick={onLogout} style={{backgroundColor: '#dc3545'}}>Logout</button>
        </div>

        <div className="breadcrumb">
            Path:
            <span onClick={() => setCurrentPath('')}> Root </span>
            {currentPath && currentPath.split('\\').map((part, index, arr) => (
                <React.Fragment key={index}>
                     \ <span onClick={() => setCurrentPath(arr.slice(0, index + 1).join('\\'))}>{part}</span>
                </React.Fragment>
            ))}
        </div>

        <div style={{marginBottom: '15px', display: 'flex', gap: '10px'}}>
            <button onClick={handleUp} disabled={!currentPath}>Up Level</button>
            <button onClick={() => loadFiles(currentPath)}>Refresh</button>
            <input
                type="file"
                onChange={handleUpload}
                disabled={uploading}
                style={{display: 'none'}}
                id="upload-input"
            />
            <label htmlFor="upload-input" style={{
                padding: '10px 15px',
                backgroundColor: '#28a745',
                color: 'white',
                borderRadius: '4px',
                cursor: 'pointer',
                marginBottom: 0
            }}>
                {uploading ? 'Uploading...' : 'Upload File'}
            </label>
        </div>

        <div style={{marginBottom: '15px', display: 'flex', gap: '10px'}}>
             <input
                type="text"
                placeholder="New Folder Name"
                value={newFolderName}
                onChange={(e) => setNewFolderName(e.target.value)}
             />
             <button onClick={handleCreateFolder} disabled={!newFolderName}>Create Folder</button>
        </div>

        {error && <p style={{color: 'red'}}>{error}</p>}
        {loading && <p>Loading...</p>}

        <ul className="file-list">
          {files.map((file) => (
            <li key={file.name} className="file-item">
              <div className="file-info" onClick={() => file.isDirectory && handleNavigate(file.name)}>
                <span className="file-icon">{file.isDirectory ? '📁' : '📄'}</span>
                <span>{file.name}</span>
                <span style={{marginLeft: 'auto', color: '#666', fontSize: '0.8em'}}>
                    {file.isDirectory ? '' : `${(file.size / 1024).toFixed(2)} KB`}
                </span>
              </div>
              <div className="actions">
                {!file.isDirectory && (
                    <button onClick={() => handleDownload(file.name)}>Download</button>
                )}
                <button onClick={() => handleRename(file.name)}>Rename</button>
                <button onClick={() => handleDelete(file.name, file.isDirectory)} style={{backgroundColor: '#dc3545'}}>Delete</button>
              </div>
            </li>
          ))}
          {files.length === 0 && !loading && <li>No files found</li>}
        </ul>
      </div>
    </div>
  );
}

export default FileManager;
