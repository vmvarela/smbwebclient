import { create } from 'zustand';
import { login, listFiles, uploadFile, downloadFile, createFolder, renameFile, deleteFile } from './api.js';

interface FileItem {
    name: string;
    isDirectory: boolean;
    size: number;
    lastModified: string;
}

interface AppState {
    // Auth
    isAuthenticated: boolean;
    token: string | null;
    user: any | null;
    loginError: string | null;
    login: (credentials: any) => Promise<boolean>;
    logout: () => void;

    // File Explorer
    currentPath: string;
    files: FileItem[];
    loading: boolean;
    error: string | null;
    fetchFiles: (path?: string) => Promise<void>;
    navigate: (path: string) => Promise<void>;
    navigateUp: () => Promise<void>;

    // Actions
    upload: (filePath: string) => Promise<void>;
    download: (fileName: string, destPath: string) => Promise<void>;
    mkdir: (folderName: string) => Promise<void>;
    rename: (oldName: string, newName: string) => Promise<void>;
    remove: (fileName: string, isDirectory: boolean) => Promise<void>;
}

export const useStore = create<AppState>((set, get) => ({
    // Auth
    isAuthenticated: false,
    token: null,
    user: null,
    loginError: null,
    login: async (credentials) => {
        try {
            set({ loading: true, loginError: null });
            const response = await login(credentials);
            set({
                isAuthenticated: true,
                token: response.data.token,
                user: credentials,
                loading: false
            });
            await get().fetchFiles('');
            return true;
        } catch (error: any) {
            set({
                loginError: error.response?.data?.error || error.message,
                loading: false
            });
            return false;
        }
    },
    logout: () => {
        set({ isAuthenticated: false, token: null, user: null, currentPath: '', files: [] });
    },

    // File Explorer
    currentPath: '',
    files: [],
    loading: false,
    error: null,
    fetchFiles: async (path = '') => {
        try {
            set({ loading: true, error: null });
            const response = await listFiles(path);
            set({ files: response.data, currentPath: path, loading: false });
        } catch (error: any) {
            set({ error: error.message, loading: false });
        }
    },
    navigate: async (folderName) => {
        const { currentPath, fetchFiles } = get();
        const newPath = currentPath ? `${currentPath}\\${folderName}` : folderName;
        await fetchFiles(newPath);
    },
    navigateUp: async () => {
        const { currentPath, fetchFiles } = get();
        if (!currentPath) return;
        const parts = currentPath.split('\\');
        parts.pop();
        const newPath = parts.join('\\');
        await fetchFiles(newPath);
    },

    // Actions
    upload: async (localFilePath) => {
        try {
            set({ loading: true, error: null });
            const { currentPath, fetchFiles } = get();
            await uploadFile(currentPath, localFilePath);
            await fetchFiles(currentPath);
        } catch (error: any) {
             set({ error: error.message, loading: false });
        }
    },
    download: async (fileName, destPath) => {
         try {
            set({ loading: true, error: null });
            const { currentPath } = get();
            const fullPath = currentPath ? `${currentPath}\\${fileName}` : fileName;
            await downloadFile(fullPath, destPath);
            set({ loading: false });
        } catch (error: any) {
             set({ error: error.message, loading: false });
        }
    },
    mkdir: async (folderName) => {
        try {
            set({ loading: true, error: null });
            const { currentPath, fetchFiles } = get();
            const fullPath = currentPath ? `${currentPath}\\${folderName}` : folderName;
            await createFolder(fullPath);
            await fetchFiles(currentPath);
        } catch (error: any) {
             set({ error: error.message, loading: false });
        }
    },
    rename: async (oldName, newName) => {
        try {
            set({ loading: true, error: null });
            const { currentPath, fetchFiles } = get();
            const oldPath = currentPath ? `${currentPath}\\${oldName}` : oldName;
            const newPath = currentPath ? `${currentPath}\\${newName}` : newName;
            await renameFile(oldPath, newPath);
            await fetchFiles(currentPath);
        } catch (error: any) {
             set({ error: error.message, loading: false });
        }
    },
    remove: async (fileName, isDirectory) => {
        try {
            set({ loading: true, error: null });
            const { currentPath, fetchFiles } = get();
            const fullPath = currentPath ? `${currentPath}\\${fileName}` : fileName;
            await deleteFile(fullPath, isDirectory);
            await fetchFiles(currentPath);
        } catch (error: any) {
             set({ error: error.message, loading: false });
        }
    }
}));
