import React, { useState } from 'react';
import { Box, Text, useInput } from 'ink';
import TextInput from 'ink-text-input';
import { useStore } from '../store.js';

interface ActionPanelProps {
    isActive: boolean;
    onClose: () => void;
    selectedFile: any | null;
}

const ActionPanel = ({ isActive, onClose, selectedFile }: ActionPanelProps) => {
    const { upload, download, mkdir, rename, remove } = useStore();
    const [action, setAction] = useState<string | null>(null); // 'upload', 'download', 'mkdir', 'rename', 'delete'
    const [inputValue, setInputValue] = useState('');
    const [menuIndex, setMenuIndex] = useState(0);

    const menuItems = [
        { label: 'Upload', value: 'upload' },
        { label: 'Create Folder', value: 'mkdir' },
        ...(selectedFile ? [
            { label: 'Download', value: 'download' },
            { label: 'Rename', value: 'rename' },
            { label: 'Delete', value: 'delete' }
        ] : [])
    ];

    useInput((input, key) => {
        if (!isActive) return;

        if (!action) {
            // Navigation within menu
            if (key.upArrow) {
                setMenuIndex(Math.max(0, menuIndex - 1));
            } else if (key.downArrow) {
                setMenuIndex(Math.min(menuItems.length - 1, menuIndex + 1));
            } else if (key.return) {
                const selectedAction = menuItems[menuIndex].value;
                if (selectedAction === 'delete') {
                    // Immediate action for delete (maybe add confirmation later)
                    remove(selectedFile.name, selectedFile.isDirectory);
                    onClose();
                } else {
                    setAction(selectedAction);
                    setInputValue(selectedAction === 'rename' ? selectedFile.name : '');
                }
            } else if (key.escape) {
                onClose();
            }
        } else {
            // Action input handling
            if (key.return) {
                handleActionSubmit();
            } else if (key.escape) {
                setAction(null);
                setInputValue('');
            }
        }
    });

    const handleActionSubmit = async () => {
        if (action === 'upload') {
            await upload(inputValue);
        } else if (action === 'mkdir') {
            await mkdir(inputValue);
        } else if (action === 'download') {
            await download(selectedFile.name, inputValue);
        } else if (action === 'rename') {
            await rename(selectedFile.name, inputValue);
        }
        setAction(null);
        setInputValue('');
        onClose();
    };

    if (!isActive) return null;

    return (
        <Box flexDirection="column" borderStyle="double" borderColor="yellow" padding={1} position="absolute" height={10}>
            {!action ? (
                <>
                    <Text bold>Actions {selectedFile ? `for ${selectedFile.name}` : ''}</Text>
                    {menuItems.map((item, index) => (
                        <Text key={item.value} color={index === menuIndex ? 'green' : 'white'}>
                            {index === menuIndex ? '> ' : '  '}{item.label}
                        </Text>
                    ))}
                    <Text color="gray">Esc to cancel</Text>
                </>
            ) : (
                <Box flexDirection="column">
                     <Text bold>{action.toUpperCase()}</Text>
                     <Text>
                        {action === 'upload' ? 'Enter local file path:' :
                         action === 'mkdir' ? 'Enter folder name:' :
                         action === 'download' ? 'Enter destination path (including filename):' :
                         action === 'rename' ? 'Enter new name:' : ''}
                     </Text>
                     <TextInput value={inputValue} onChange={setInputValue} focus={true} />
                </Box>
            )}
        </Box>
    );
};

export default ActionPanel;
