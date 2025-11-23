import React, { useState, useEffect } from 'react';
import {
  StyleSheet,
  View,
  Text,
  FlatList,
  TouchableOpacity,
  Alert,
  TextInput,
  Platform,
  Share,
  Modal,
  Button
} from 'react-native';
import { listFiles, createFolder, renameFile, deleteFile, uploadFile, getDownloadUrl } from '../api';
import * as DocumentPicker from 'expo-document-picker';
import * as FileSystem from 'expo-file-system';
import * as Sharing from 'expo-sharing';

export default function FileManager({ onLogout }) {
  const [files, setFiles] = useState([]);
  const [currentPath, setCurrentPath] = useState('');
  const [loading, setLoading] = useState(false);
  const [newFolderName, setNewFolderName] = useState('');
  const [uploading, setUploading] = useState(false);

  // State for Rename Modal
  const [renameModalVisible, setRenameModalVisible] = useState(false);
  const [fileToRename, setFileToRename] = useState(null);
  const [newName, setNewName] = useState('');

  useEffect(() => {
    loadFiles(currentPath);
  }, [currentPath]);

  const loadFiles = async (path) => {
    setLoading(true);
    try {
      const response = await listFiles(path);
      setFiles(response.data);
    } catch (err) {
      Alert.alert('Error', err.response?.data?.error || 'Failed to load files');
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
    const downloadUrl = getDownloadUrl(path);
    const fileUri = FileSystem.documentDirectory + fileName;

    try {
      setLoading(true);
      const { uri } = await FileSystem.downloadAsync(downloadUrl, fileUri);

      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(uri);
      } else {
        Alert.alert('Success', `File downloaded to ${uri}`);
      }
    } catch (err) {
      Alert.alert('Error', 'Download failed');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleUpload = async () => {
    try {
      const result = await DocumentPicker.getDocumentAsync({});

      if (result.canceled) return;

      const asset = result.assets[0];
      setUploading(true);

      const formData = new FormData();
      formData.append('file', {
        uri: asset.uri,
        name: asset.name,
        type: asset.mimeType || 'application/octet-stream',
      });
      formData.append('path', currentPath);

      await uploadFile(formData);
      loadFiles(currentPath);
      Alert.alert('Success', 'File uploaded successfully');
    } catch (err) {
      Alert.alert('Error', 'Upload failed: ' + (err.response?.data?.error || err.message));
    } finally {
      setUploading(false);
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
      Alert.alert('Error', 'Failed to create folder: ' + (err.response?.data?.error || err.message));
    }
  };

  const handleDelete = async (fileName, isDirectory) => {
    Alert.alert(
      'Confirm Delete',
      `Are you sure you want to delete ${fileName}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            const path = currentPath ? `${currentPath}\\${fileName}` : fileName;
            try {
              await deleteFile(path, isDirectory);
              loadFiles(currentPath);
            } catch (err) {
              Alert.alert('Error', 'Failed to delete: ' + (err.response?.data?.error || err.message));
            }
          }
        }
      ]
    );
  };

  const promptRename = (fileName) => {
    setFileToRename(fileName);
    setNewName(fileName);
    setRenameModalVisible(true);
  };

  const confirmRename = async () => {
    if (!newName || newName === fileToRename) {
      setRenameModalVisible(false);
      return;
    }

    const oldPath = currentPath ? `${currentPath}\\${fileToRename}` : fileToRename;
    const newPath = currentPath ? `${currentPath}\\${newName}` : newName;

    try {
      await renameFile(oldPath, newPath);
      loadFiles(currentPath);
    } catch (err) {
      Alert.alert('Error', 'Failed to rename: ' + (err.response?.data?.error || err.message));
    } finally {
      setRenameModalVisible(false);
      setFileToRename(null);
      setNewName('');
    }
  };

  const renderItem = ({ item }) => (
    <View style={styles.fileItem}>
      <TouchableOpacity
        style={styles.fileInfo}
        onPress={() => item.isDirectory && handleNavigate(item.name)}
      >
        <Text style={styles.fileIcon}>{item.isDirectory ? '📁' : '📄'}</Text>
        <View style={styles.fileDetails}>
          <Text style={styles.fileName}>{item.name}</Text>
          {!item.isDirectory && (
            <Text style={styles.fileSize}>{(item.size / 1024).toFixed(2)} KB</Text>
          )}
        </View>
      </TouchableOpacity>

      <View style={styles.actions}>
        {!item.isDirectory && (
          <TouchableOpacity onPress={() => handleDownload(item.name)} style={styles.actionBtn}>
             <Text style={styles.actionText}>⬇️</Text>
          </TouchableOpacity>
        )}
        <TouchableOpacity onPress={() => promptRename(item.name)} style={styles.actionBtn}>
           <Text style={styles.actionText}>✏️</Text>
        </TouchableOpacity>
        <TouchableOpacity onPress={() => handleDelete(item.name, item.isDirectory)} style={styles.actionBtn}>
           <Text style={styles.actionText}>🗑️</Text>
        </TouchableOpacity>
      </View>
    </View>
  );

  return (
    <View style={styles.container}>
      <Modal
        animationType="slide"
        transparent={true}
        visible={renameModalVisible}
        onRequestClose={() => setRenameModalVisible(false)}
      >
        <View style={styles.centeredView}>
          <View style={styles.modalView}>
            <Text style={styles.modalText}>Rename File</Text>
            <TextInput
              style={styles.modalInput}
              onChangeText={setNewName}
              value={newName}
              autoFocus={true}
            />
            <View style={styles.modalButtons}>
              <Button title="Cancel" onPress={() => setRenameModalVisible(false)} color="#dc3545" />
              <Button title="Rename" onPress={confirmRename} />
            </View>
          </View>
        </View>
      </Modal>

      <View style={styles.header}>
        <Text style={styles.headerTitle}>File Manager</Text>
        <TouchableOpacity onPress={onLogout} style={styles.logoutBtn}>
          <Text style={styles.logoutText}>Logout</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.breadcrumb}>
        <Text>Path: </Text>
        <TouchableOpacity onPress={() => setCurrentPath('')}>
           <Text style={styles.breadcrumbLink}>Root</Text>
        </TouchableOpacity>
        {currentPath.split('\\').map((part, index, arr) => {
           if (!part) return null;
           return (
             <React.Fragment key={index}>
               <Text> \ </Text>
               <TouchableOpacity onPress={() => setCurrentPath(arr.slice(0, index + 1).join('\\'))}>
                 <Text style={styles.breadcrumbLink}>{part}</Text>
               </TouchableOpacity>
             </React.Fragment>
           );
        })}
      </View>

      <View style={styles.controls}>
        <TouchableOpacity
          onPress={handleUp}
          disabled={!currentPath}
          style={[styles.controlBtn, !currentPath && styles.disabledBtn]}
        >
          <Text style={styles.controlBtnText}>Up Level</Text>
        </TouchableOpacity>
        <TouchableOpacity onPress={() => loadFiles(currentPath)} style={styles.controlBtn}>
          <Text style={styles.controlBtnText}>Refresh</Text>
        </TouchableOpacity>
        <TouchableOpacity onPress={handleUpload} disabled={uploading} style={[styles.controlBtn, styles.uploadBtn]}>
           <Text style={styles.controlBtnText}>{uploading ? 'Uploading...' : 'Upload File'}</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.createFolderContainer}>
        <TextInput
          style={styles.folderInput}
          placeholder="New Folder Name"
          value={newFolderName}
          onChangeText={setNewFolderName}
        />
        <TouchableOpacity
          onPress={handleCreateFolder}
          disabled={!newFolderName}
          style={[styles.controlBtn, !newFolderName && styles.disabledBtn]}
        >
          <Text style={styles.controlBtnText}>Create</Text>
        </TouchableOpacity>
      </View>

      {loading && <ActivityIndicator size="large" color="#0000ff" style={{margin: 20}} />}

      <FlatList
        data={files}
        renderItem={renderItem}
        keyExtractor={(item) => item.name}
        ListEmptyComponent={!loading && <Text style={styles.emptyText}>No files found</Text>}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
    padding: 10,
    paddingTop: 50, // Status bar padding
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
    paddingBottom: 10,
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: 'bold',
  },
  logoutBtn: {
    backgroundColor: '#dc3545',
    padding: 8,
    borderRadius: 5,
  },
  logoutText: {
    color: 'white',
    fontWeight: 'bold',
  },
  breadcrumb: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginBottom: 15,
    padding: 10,
    backgroundColor: '#f8f9fa',
    borderRadius: 5,
  },
  breadcrumbLink: {
    color: '#007bff',
    textDecorationLine: 'underline',
  },
  controls: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 15,
  },
  controlBtn: {
    padding: 10,
    backgroundColor: '#007bff',
    borderRadius: 5,
    alignItems: 'center',
  },
  controlBtnText: {
    color: 'white',
  },
  disabledBtn: {
    backgroundColor: '#ccc',
  },
  uploadBtn: {
    backgroundColor: '#28a745',
  },
  createFolderContainer: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 20,
  },
  folderInput: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#ddd',
    padding: 10,
    borderRadius: 5,
  },
  fileItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 15,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  fileInfo: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
  },
  fileIcon: {
    fontSize: 24,
    marginRight: 10,
  },
  fileDetails: {
    flex: 1,
  },
  fileName: {
    fontSize: 16,
  },
  fileSize: {
    fontSize: 12,
    color: '#666',
  },
  actions: {
    flexDirection: 'row',
    gap: 10,
  },
  actionBtn: {
    padding: 5,
  },
  actionText: {
    fontSize: 18,
  },
  emptyText: {
    textAlign: 'center',
    marginTop: 20,
    color: '#666',
  },
  centeredView: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    marginTop: 22,
    backgroundColor: 'rgba(0,0,0,0.5)'
  },
  modalView: {
    margin: 20,
    backgroundColor: "white",
    borderRadius: 20,
    padding: 35,
    alignItems: "center",
    shadowColor: "#000",
    shadowOffset: {
      width: 0,
      height: 2
    },
    shadowOpacity: 0.25,
    shadowRadius: 4,
    elevation: 5,
    width: '80%'
  },
  modalText: {
    marginBottom: 15,
    textAlign: "center",
    fontSize: 20,
    fontWeight: 'bold'
  },
  modalInput: {
    borderWidth: 1,
    borderColor: '#ccc',
    padding: 10,
    width: '100%',
    marginBottom: 20,
    borderRadius: 5
  },
  modalButtons: {
    flexDirection: 'row',
    gap: 20
  }
});
