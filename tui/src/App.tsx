import React, { useState } from 'react';
import { Box, useInput, Text } from 'ink';
import { useStore } from './store.js';
import Login from './components/Login.js';
import Header from './components/Header.js';
import FileList from './components/FileList.js';
import ActionPanel from './components/ActionPanel.js';

const App = () => {
    const { isAuthenticated, logout } = useStore();
    const [uiMode, setUiMode] = useState('browsing'); // 'browsing', 'modal'
    const [selectedFile, setSelectedFile] = useState(null);

    useInput((input, key) => {
        if (!isAuthenticated) return;

        if (uiMode === 'browsing') {
            if (key.ctrl && input === 'a') { // Ctrl+A for Actions
                setUiMode('modal');
                setSelectedFile(null); // General actions
            } else if (key.escape) {
                 // logout();
            }
        }
    });

    const handleSelectFile = (file: any) => {
        setSelectedFile(file);
        setUiMode('modal');
    };

    const handleCloseModal = () => {
        setUiMode('browsing');
        setSelectedFile(null);
    };

    if (!isAuthenticated) {
        return <Login />;
    }

    return (
        <Box flexDirection="column" height="100%">
            <Header />
            <Box flexGrow={1}>
                <FileList isActive={uiMode === 'browsing'} onSelectFile={handleSelectFile} />
            </Box>
            {uiMode === 'modal' && (
                <ActionPanel isActive={true} onClose={handleCloseModal} selectedFile={selectedFile} />
            )}
             <Box borderStyle="single" paddingX={1}>
                <Text>Ctrl+A: Actions | Arrows: Navigate | Enter: Open/Select | Backspace: Up</Text>
            </Box>
        </Box>
    );
};

export default App;
