import axios from 'axios';
import fs from 'fs';
import FormData from 'form-data';
import dotenv from 'dotenv';

dotenv.config();

// Using localhost:3000 as default base URL.
const BASE_URL = process.env.API_URL || 'http://localhost:3000/api';

let token: string | null = null;

const api = axios.create({
  baseURL: BASE_URL,
});

api.interceptors.request.use((config) => {
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && (error.response.status === 401 || error.response.status === 403)) {
        // Token expired or invalid
        token = null;
        // Ideally trigger a re-login flow or throw a specific error
    }
    return Promise.reject(error);
  }
);

export const setToken = (newToken: string) => {
    token = newToken;
};

export const getToken = () => token;

export const login = async (credentials: any) => {
    const response = await api.post('/login', credentials);
    if (response.data.token) {
        setToken(response.data.token);
    }
    return response;
};

export const listFiles = (path: string) => api.get('/files', { params: { path } });

export const downloadFile = async (path: string, destinationPath: string) => {
    const response = await api.get('/download', { params: { path }, responseType: 'stream' });
    const writer = fs.createWriteStream(destinationPath);
    response.data.pipe(writer);
    return new Promise<void>((resolve, reject) => {
        writer.on('finish', () => resolve());
        writer.on('error', reject);
    });
};

export const uploadFile = (path: string, filePath: string) => {
    const formData = new FormData();
    formData.append('path', path);
    formData.append('file', fs.createReadStream(filePath));

    return api.post('/upload', formData, {
        headers: {
            ...formData.getHeaders()
        }
    });
};

export const createFolder = (path: string) => api.post('/mkdir', { path });

export const renameFile = (oldPath: string, newPath: string) => api.post('/rename', { oldPath, newPath });

export const deleteFile = (path: string, isDirectory: boolean) => api.post('/delete', { path, isDirectory });

export default api;
