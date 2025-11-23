import React, { useState, useEffect } from 'react';
import { Box, Text, useInput } from 'ink';
import SelectInput from 'ink-select-input';
import Spinner from 'ink-spinner';
import { api } from '../api.js';
import TextInput from 'ink-text-input';

interface FileItem {
    name: string;
    type: 'file' | 'folder';
    size: string;
}

interface FileExplorerProps {
    onLogout: () => void;
}

const FileExplorer: React.FC<FileExplorerProps> = ({ onLogout }) => {
    const [path, setPath] = useState('');
    const [files, setFiles] = useState<FileItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [mode, setMode] = useState<'list' | 'rename' | 'upload' | 'mkdir'>('list');
    const [selectedFile, setSelectedFile] = useState<FileItem | null>(null);
    const [inputText, setInputText] = useState('');
    const [message, setMessage] = useState('');

    useEffect(() => {
        loadFiles();
    }, [path]);

    const loadFiles = async () => {
        setLoading(true);
        try {
            const data = await api.listFiles(path);
            // Add '..' for navigation if not root
            const items = path ? [{ name: '..', type: 'folder', size: '' }, ...data] : data;
            setFiles(items);
        } catch (err) {
            setMessage('Error loading files');
        }
        setLoading(false);
    };

    const handleSelect = (item: any) => {
        const file = files.find(f => f.name === item.value);
        if (!file) return;

        if (file.name === '..') {
            const parts = path.split('/');
            parts.pop();
            setPath(parts.join('/'));
        } else if (file.type === 'folder') {
            setPath(path ? `${path}/${file.name}` : file.name);
        } else {
            // Selected a file
            setSelectedFile(file);
        }
    };

    useInput((input, key) => {
        if (mode !== 'list') return;

        if (key.delete && selectedFile) {
            // Delete file
            setLoading(true);
            api.deleteFile(path ? `${path}/${selectedFile.name}` : selectedFile.name)
                .then(() => {
                    setMessage(`Deleted ${selectedFile.name}`);
                    loadFiles();
                    setSelectedFile(null);
                })
                .catch(() => setMessage('Delete failed'));
        }

        if (input === 'r' && selectedFile) {
            setMode('rename');
            setInputText(selectedFile.name);
        }

        if (input === 'u') {
            setMode('upload');
            setInputText('');
        }

        if (input === 'n') {
            setMode('mkdir');
            setInputText('');
        }

        if (input === 'l') {
            onLogout();
        }
    });

    const handleInputSubmit = async () => {
        if (mode === 'rename' && selectedFile) {
            await api.renameFile(path ? `${path}/${selectedFile.name}` : selectedFile.name, inputText);
            setMessage(`Renamed to ${inputText}`);
        } else if (mode === 'mkdir') {
            await api.createDirectory(path, inputText);
            setMessage(`Created directory ${inputText}`);
        } else if (mode === 'upload') {
            // Mock upload, expecting full local path
            await api.uploadFile(path, inputText);
            setMessage(`Uploaded ${inputText}`);
        }

        setMode('list');
        loadFiles();
    };

    if (loading) {
        return <Box><Text color="green"><Spinner type="dots" /> Loading...</Text></Box>;
    }

    const items = files.map(file => ({
        label: `${file.type === 'folder' ? '[DIR]' : '[FILE]'} ${file.name} (${file.size})`,
        value: file.name
    }));

    return (
        <Box flexDirection="column" padding={1}>
            <Text>Path: /{path}</Text>
            <Box borderColor="gray" borderStyle="single" padding={1} flexDirection="column">
                {files.length === 0 ? (
                    <Text>No files found.</Text>
                ) : (
                    mode === 'list' ? (
                        <SelectInput
                            items={items}
                            onSelect={handleSelect}
                            onHighlight={(item) => {
                                const file = files.find(f => f.name === item.value);
                                setSelectedFile(file || null);
                            }}
                        />
                    ) : (
                        <Box>
                            <Text color="gray">
                                {files.map(f => f.name).join('\n').substring(0, 100)}... (List hidden during input)
                            </Text>
                        </Box>
                    )
                )}
            </Box>

            {message && <Text color="yellow">{message}</Text>}

            <Box marginTop={1}>
                <Text>
                    [Enter] Open  [Delete] Delete  [r] Rename  [n] New Folder  [u] Upload [l] Logout
                </Text>
            </Box>

            {mode !== 'list' && (
                <Box marginTop={1}>
                    <Text>{mode === 'rename' ? 'New Name: ' : mode === 'mkdir' ? 'Folder Name: ' : 'Local File Path: '}</Text>
                    <TextInput
                        value={inputText}
                        onChange={setInputText}
                        onSubmit={handleInputSubmit}
                        focus={true}
                    />
                </Box>
            )}
        </Box>
    );
};

export default FileExplorer;
