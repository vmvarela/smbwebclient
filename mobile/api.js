import axios from 'axios';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const BASE_URL = Platform.OS === 'android'
  ? 'http://10.0.2.2:3000/api'
  : 'http://localhost:3000/api';

const api = axios.create({
  baseURL: BASE_URL,
});

api.interceptors.request.use(async (config) => {
  const token = await SecureStore.getItemAsync('smb_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response && (error.response.status === 401 || error.response.status === 403)) {
      await SecureStore.deleteItemAsync('smb_token');
    }
    return Promise.reject(error);
  }
);

export const login = (credentials) => api.post('/login', credentials);
export const listFiles = (path) => api.get('/files', { params: { path } });
export const getDownloadUrl = (path) => `${BASE_URL}/download?path=${encodeURIComponent(path)}`;

export const uploadFile = (formData) => api.post('/upload', formData, {
  headers: { 'Content-Type': 'multipart/form-data' }
});
export const createFolder = (path) => api.post('/mkdir', { path });
export const renameFile = (oldPath, newPath) => api.post('/rename', { oldPath, newPath });
export const deleteFile = (path, isDirectory) => api.post('/delete', { path, isDirectory });

export default api;
