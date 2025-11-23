import axios from 'axios';

const api = axios.create({
  baseURL: '/api'
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('smb_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && (error.response.status === 401 || error.response.status === 403)) {
      localStorage.removeItem('smb_token');
      window.location.reload();
    }
    return Promise.reject(error);
  }
);

export const login = (credentials) => api.post('/login', credentials);
export const listFiles = (path) => api.get('/files', { params: { path } });
export const downloadFile = (path) => api.get('/download', { params: { path }, responseType: 'blob' });
export const uploadFile = (formData) => api.post('/upload', formData, {
  headers: { 'Content-Type': 'multipart/form-data' }
});
export const createFolder = (path) => api.post('/mkdir', { path });
export const renameFile = (oldPath, newPath) => api.post('/rename', { oldPath, newPath });
export const deleteFile = (path, isDirectory) => api.post('/delete', { path, isDirectory });

export default api;
