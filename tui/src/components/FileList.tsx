import React, { useState, useEffect } from 'react';
import { Box, Text, useInput } from 'ink';
import { useStore } from '../store.js';

interface FileListProps {
    isActive: boolean;
    onSelectFile: (file: any) => void;
}

const ITEMS_PER_PAGE = 20;

const FileList = ({ isActive, onSelectFile }: FileListProps) => {
    const { files, navigate, navigateUp } = useStore();
    const [selectedIndex, setSelectedIndex] = useState(0);

    useEffect(() => {
        setSelectedIndex(0);
    }, [files]);

    useInput((input, key) => {
        if (!isActive) return;

        if (key.upArrow) {
            setSelectedIndex(Math.max(0, selectedIndex - 1));
        } else if (key.downArrow) {
            setSelectedIndex(Math.min(files.length - 1, selectedIndex + 1));
        } else if (key.return) {
            const file = files[selectedIndex];
            if (file) {
                if (file.isDirectory) {
                    navigate(file.name);
                } else {
                    onSelectFile(file); // Trigger action panel or download
                }
            }
        } else if (key.delete || key.backspace) {
             navigateUp();
        }
    });

    if (files.length === 0) {
        return (
            <Box borderStyle="single" padding={1}>
                <Text>No files found.</Text>
            </Box>
        );
    }

    const startIndex = Math.floor(selectedIndex / ITEMS_PER_PAGE) * ITEMS_PER_PAGE;
    const visibleFiles = files.slice(startIndex, startIndex + ITEMS_PER_PAGE);
    const visibleSelectedIndex = selectedIndex - startIndex;

    return (
        <Box flexDirection="column" borderStyle="single" padding={1} flexGrow={1}>
             <Box marginBottom={1}>
                <Text underline>Name</Text>
                <Box flexGrow={1} />
                <Text underline>Size</Text>
            </Box>
            {visibleFiles.map((file, index) => (
                <Box key={file.name} justifyContent="space-between">
                    <Text
                        color={index === visibleSelectedIndex ? (isActive ? 'cyan' : 'blue') : 'white'}
                        backgroundColor={index === visibleSelectedIndex && isActive ? 'gray' : undefined}
                    >
                        {file.isDirectory ? '[DIR] ' : '      '}{file.name}
                    </Text>
                    <Text>{file.size}</Text>
                </Box>
            ))}
             <Box marginTop={1} borderStyle="single" borderColor="gray">
                <Text>Page {Math.floor(selectedIndex / ITEMS_PER_PAGE) + 1} of {Math.ceil(files.length / ITEMS_PER_PAGE)}</Text>
            </Box>
        </Box>
    );
};

export default FileList;
