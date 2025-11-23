import axios from 'axios';
import FormData from 'form-data';
import fs from 'fs';

const BASE_URL = 'http://localhost:3000';

// Note: This API client assumes the backend provides a JSON API or a compatible interface.
// If the backend is pure HTML (like smbwebclient.php), this client would need to parse HTML,
// but for the purpose of this task, we are implementing the client structure.

export const api = {
    login: async (username: string, password: string) => {
        // Mocking login for now if backend is not available
        // In a real scenario:
        // const response = await axios.post(`${BASE_URL}/api/login`, { username, password });
        // return response.data;
        return { success: true, token: 'mock-token' };
    },

    listFiles: async (path: string = '') => {
        try {
            const response = await axios.get(`${BASE_URL}/api/files`, { params: { path } });
            return response.data;
        } catch (error) {
             // Mock data for development if backend is not running
             return [
                 { name: 'Document.txt', type: 'file', size: '12KB' },
                 { name: 'Photos', type: 'folder', size: '-' },
                 { name: 'Work', type: 'folder', size: '-' },
                 { name: 'notes.md', type: 'file', size: '2KB' }
             ];
        }
    },

    createDirectory: async (path: string, name: string) => {
        await axios.post(`${BASE_URL}/api/directory`, { path, name });
    },

    deleteFile: async (path: string) => {
        await axios.delete(`${BASE_URL}/api/files`, { data: { path } });
    },

    renameFile: async (path: string, newName: string) => {
        await axios.put(`${BASE_URL}/api/files/rename`, { path, newName });
    },

    uploadFile: async (destinationPath: string, filePath: string) => {
        const formData = new FormData();
        formData.append('file', fs.createReadStream(filePath));
        formData.append('path', destinationPath);

        await axios.post(`${BASE_URL}/api/upload`, formData, {
            headers: formData.getHeaders()
        });
    }
};
